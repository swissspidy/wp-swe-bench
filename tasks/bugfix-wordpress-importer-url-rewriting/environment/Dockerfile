ARG WPSB_BASE_IMAGE=ghcr.io/swissspidy/wp-swe-bench-base:0.1.0
FROM ${WPSB_BASE_IMAGE}

# The repository is the WordPress Importer plugin 0.9.3 from wordpress.org (installed by the blueprint).
ENV WPSB_REPO=/wordpress/wp-content/plugins/wordpress-importer

COPY blueprint.json seed.sh /opt/wpsb/task/
COPY seed/ /opt/wpsb/task/seed/

RUN wpsb-provision

WORKDIR /wordpress/wp-content/plugins/wordpress-importer
