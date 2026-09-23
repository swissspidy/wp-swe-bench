<?php
/**
 * URL safety checks for the fetcher (SSRF protection).
 *
 * A URL is only safe to fetch when its scheme is http(s) and the host resolves
 * to a public, routable IP address. Host names are resolved through the
 * `acme_lp_resolve_host` filter; IP literals (in any notation) are decoded
 * directly so that alternative encodings cannot slip a private address past the
 * check.
 *
 * @package Acme\LinkPreviews
 */

namespace Acme\LinkPreviews;

defined( 'ABSPATH' ) || exit;

/**
 * URL guard.
 */
class Url_Guard {

	const ALLOWED_SCHEMES = array( 'http', 'https' );

	/**
	 * Check that a URL is safe to fetch.
	 *
	 * @param string $url URL.
	 * @return true|\WP_Error
	 */
	public static function check_url( $url ) {
		$parts = wp_parse_url( (string) $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return new \WP_Error( 'acme_lp_invalid_url', __( 'That does not look like a URL.', 'acme-link-previews' ), array( 'status' => 400 ) );
		}
		if ( ! in_array( strtolower( $parts['scheme'] ), self::ALLOWED_SCHEMES, true ) ) {
			return new \WP_Error( 'acme_lp_invalid_url', __( 'Only http and https URLs can be previewed.', 'acme-link-previews' ), array( 'status' => 400 ) );
		}

		$ip = self::resolve( $parts['host'] );
		if ( '' === $ip || self::is_blocked_ip( $ip ) ) {
			return new \WP_Error( 'acme_lp_blocked_host', __( 'That address cannot be previewed.', 'acme-link-previews' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Resolve a host to a canonical IP string, or '' if it cannot be resolved.
	 *
	 * @param string $host Host from the URL (may be an IP literal in any notation).
	 * @return string
	 */
	public static function resolve( $host ) {
		$host = trim( $host );
		$host = trim( $host, '[]' );
		if ( '' === $host ) {
			return '';
		}

		$literal = self::normalize_ip_literal( $host );
		if ( null !== $literal ) {
			return $literal;
		}

		// A genuine host name: resolve it (infrastructure may override how).
		$resolved = apply_filters( 'acme_lp_resolve_host', gethostbyname( $host ), $host );
		if ( ! is_string( $resolved ) || '' === $resolved || $resolved === $host ) {
			return '';
		}
		$canon = self::normalize_ip_literal( $resolved );
		if ( null !== $canon ) {
			return $canon;
		}
		return filter_var( $resolved, FILTER_VALIDATE_IP ) ? $resolved : '';
	}

	/**
	 * Turn an IP literal (dotted, decimal, octal, hex, IPv6, IPv6-mapped) into a
	 * canonical IP string, or null if the host is not an IP literal.
	 *
	 * @param string $host Host.
	 * @return string|null
	 */
	public static function normalize_ip_literal( $host ) {
		if ( false !== strpos( $host, ':' ) ) {
			return filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ? $host : null;
		}
		return self::parse_ipv4_relaxed( $host );
	}

	/**
	 * Parse an IPv4 address written in dotted / decimal / octal / hex form the
	 * way the C library's inet_aton() does (1 to 4 parts).
	 *
	 * @param string $host Host.
	 * @return string|null Canonical dotted-quad, or null if not an IPv4 literal.
	 */
	private static function parse_ipv4_relaxed( $host ) {
		$parts = explode( '.', $host );
		$count = count( $parts );
		if ( $count < 1 || $count > 4 ) {
			return null;
		}

		$nums = array();
		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				return null;
			}
			if ( preg_match( '/^0x[0-9a-f]+$/i', $part ) ) {
				$value = hexdec( substr( $part, 2 ) );
			} elseif ( preg_match( '/^0[0-7]+$/', $part ) ) {
				$value = octdec( $part );
			} elseif ( preg_match( '/^[0-9]+$/', $part ) ) {
				$value = (int) $part;
			} else {
				return null;
			}
			$nums[] = $value;
		}

		$count = count( $nums );
		if ( 1 === $count ) {
			$ip = $nums[0];
		} elseif ( 2 === $count ) {
			if ( $nums[0] > 0xFF || $nums[1] > 0xFFFFFF ) {
				return null;
			}
			$ip = ( $nums[0] << 24 ) | $nums[1];
		} elseif ( 3 === $count ) {
			if ( $nums[0] > 0xFF || $nums[1] > 0xFF || $nums[2] > 0xFFFF ) {
				return null;
			}
			$ip = ( $nums[0] << 24 ) | ( $nums[1] << 16 ) | $nums[2];
		} else {
			foreach ( $nums as $num ) {
				if ( $num > 0xFF ) {
					return null;
				}
			}
			$ip = ( $nums[0] << 24 ) | ( $nums[1] << 16 ) | ( $nums[2] << 8 ) | $nums[3];
		}

		if ( $ip < 0 || $ip > 0xFFFFFFFF ) {
			return null;
		}
		return long2ip( $ip );
	}

	/**
	 * Whether an IP address is not a public, routable unicast address.
	 *
	 * @param string $ip Canonical IP string.
	 * @return bool
	 */
	public static function is_blocked_ip( $ip ) {
		$packed = @inet_pton( $ip );
		if ( false === $packed ) {
			return true;
		}
		$length = strlen( $packed );

		if ( 4 === $length ) {
			return self::blocked_v4( $packed );
		}

		if ( 16 === $length ) {
			// IPv4-mapped (::ffff:a.b.c.d).
			if ( "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff" === substr( $packed, 0, 12 ) ) {
				return self::blocked_v4( substr( $packed, 12, 4 ) );
			}
			// ::1 (loopback) and :: (unspecified).
			if ( str_repeat( "\x00", 15 ) . "\x01" === $packed || str_repeat( "\x00", 16 ) === $packed ) {
				return true;
			}
			$b0 = ord( $packed[0] );
			// fc00::/7 (unique local).
			if ( 0xFC === ( $b0 & 0xFE ) ) {
				return true;
			}
			// fe80::/10 (link-local).
			if ( 0xFE === $b0 && 0x80 === ( ord( $packed[1] ) & 0xC0 ) ) {
				return true;
			}
			// Only global unicast (2000::/3) is allowed; everything else (incl. ff00::/8) is blocked.
			return 0x20 !== ( $b0 & 0xE0 );
		}

		return true;
	}

	/**
	 * Whether a packed IPv4 address is in a non-public range.
	 *
	 * @param string $packed 4-byte packed address.
	 * @return bool
	 */
	private static function blocked_v4( $packed ) {
		$b = array_values( unpack( 'C4', $packed ) );
		list( $a, $b1, $c ) = $b;

		if ( 0 === $a ) {
			return true; // 0.0.0.0/8
		}
		if ( 10 === $a || 127 === $a ) {
			return true; // 10/8, loopback
		}
		if ( 169 === $a && 254 === $b1 ) {
			return true; // link-local
		}
		if ( 172 === $a && $b1 >= 16 && $b1 <= 31 ) {
			return true; // 172.16/12
		}
		if ( 192 === $a && 168 === $b1 ) {
			return true; // 192.168/16
		}
		if ( 192 === $a && 0 === $b1 && 0 === $c ) {
			return true; // 192.0.0/24
		}
		if ( 100 === $a && $b1 >= 64 && $b1 <= 127 ) {
			return true; // 100.64/10 (CGNAT)
		}
		if ( 198 === $a && ( 18 === $b1 || 19 === $b1 ) ) {
			return true; // 198.18/15 (benchmarking)
		}
		if ( $a >= 224 ) {
			return true; // 224/4 multicast + 240/4 reserved + broadcast
		}
		return false;
	}
}
