// Shared helpers for the Acme Testimonials hidden tests.
import { expect } from '@playwright/test';
import { wp } from '../wpsb/e2e/helpers.mjs';

export const postId = (slug, type = 'post') =>
	Number(wp(['post', 'list', `--post_type=${type}`, `--name=${slug}`, '--field=ID', '--post_status=any']));

export const postContent = (id) => wp(['post', 'get', String(id), '--field=post_content']);

// Every seeded testimonial, in document order, as it must look in the 4.x format.
// rating 0 = not rated; avatar = substring of the image URL (null = no avatar).
export const SEEDED = {
	'v1-testimonials': {
		type: 'post',
		items: [
			{ quote: 'Acme Suite saved us <strong>two days a week</strong>. See <a href="https://example.org/case-study">our case study</a>.', name: 'Jane Doe', role: 'CTO at Initech', rating: 0, avatar: null, classes: [] },
			{ quote: 'Simply the best.', name: 'Anonymous', role: null, rating: 0, avatar: null, classes: ['featured-quote'] },
			{ quote: 'Fast, friendly and fair.<br>We will be back.', name: 'Bob Smith', role: 'Head of Sales, EMEA', rating: 0, avatar: null, classes: [] },
		],
	},
	'v2-testimonials': {
		type: 'post',
		items: [
			{ quote: 'Our onboarding time dropped by half.', name: 'Maria Garcia', role: 'HR Director, Globex', rating: 4, avatar: null, classes: ['alignwide'] },
			{ quote: 'Great value for money.', name: 'Tom Becker', role: 'Owner, Becker &amp; Sons', rating: 4.5, avatar: null, classes: [] },
			{ quote: 'I <em>love</em> the <a href="https://example.org/reports">reports</a>.', name: 'Priya Natarajan', role: null, rating: 0, avatar: null, classes: ['is-style-card'] },
			{ quote: 'Not for us, but well made.', name: 'Sam Lee', role: 'Student', rating: 0, avatar: null, classes: [] },
		],
	},
	'v3-testimonials': {
		type: 'post',
		items: [
			{ quote: 'Rock solid for three years now.', name: 'Jane Doe', role: 'CTO, Initech', rating: 5, avatar: '/jane-doe.jpg', classes: ['alignright', 'is-style-card'] },
			{ quote: 'Does what it says on the tin.', name: 'Kim Nguyen', role: null, rating: 0, avatar: null, classes: [] },
			{ quote: 'Support could be faster, the product is great.', name: 'Luis Romero', role: 'IT manager', rating: 3, avatar: null, classes: [] },
		],
	},
	'mixed-testimonials': {
		type: 'page',
		items: [
			{ quote: 'We migrated 40 sites in a weekend.', name: 'Alex Chen', role: 'Lead developer, Umbrella Corp', rating: 0, avatar: null, classes: [] },
			{ quote: 'Five stars, no notes.', name: 'Grace Hopper', role: 'Admiral', rating: 5, avatar: null, classes: [] },
			{ quote: 'Too expensive for small teams.', name: 'Pat Doe', role: null, rating: 2, avatar: null, classes: [] },
		],
	},
	'customer-reviews': {
		type: 'page',
		items: [
			{ quote: 'Setup took ten minutes. Support answered on a Sunday!', name: 'Omar Haddad', role: 'Founder, Haddad Bakery', rating: 5, avatar: '/omar-haddad.jpg', classes: [] },
			{ quote: 'We replaced three tools with Acme Suite and our team actually likes it.', name: 'Lena Fischer', role: 'Operations lead, Nordwind GmbH', rating: 4, avatar: null, classes: [] },
			{ quote: 'Solid product. The mobile app still needs work.', name: 'Chris Park', role: null, rating: 3, avatar: null, classes: [] },
			{ quote: "I don't write reviews, but this one is deserved.", name: 'Ana Souza', role: 'Freelance designer', rating: 0, avatar: null, classes: [] },
		],
	},
};

/**
 * Describe the testimonial markup found in an HTML string (runs in the browser).
 * Returns one entry per `.wp-block-acme-testimonial` element.
 */
export function describeInPage(page, html) {
	return page.evaluate((source) => {
		const doc = new DOMParser().parseFromString(`<body>${source}</body>`, 'text/html');
		// The front end curls apostrophes (wptexturize); compare them as typed.
		const plain = (h) => (h === null ? null : h.replace(/\u2019/g, "'"));
		return [...doc.querySelectorAll('.wp-block-acme-testimonial')].map((root) => {
			const q = root.querySelector(':scope > blockquote.acme-testimonial__quote > p');
			const byline = root.querySelector(':scope > figcaption.acme-testimonial__byline');
			const name = byline && byline.querySelector('.acme-testimonial__name');
			const role = byline && byline.querySelector('.acme-testimonial__role');
			const imgs = [...root.querySelectorAll('img')];
			const avatar = byline && byline.querySelector(':scope > img.acme-testimonial__avatar');
			const rating = root.querySelector(':scope > .acme-testimonial__rating');
			return {
				tag: root.tagName.toLowerCase(),
				classes: [...root.classList],
				childTags: [...root.children].map((c) => c.tagName.toLowerCase()),
				quote: q ? plain(q.innerHTML) : null,
				hasByline: !!byline,
				nameTag: name ? name.tagName.toLowerCase() : null,
				name: name ? plain(name.innerHTML) : null,
				nameIsFirstText: name && byline ? [...byline.children].filter((c) => c.tagName !== 'IMG')[0] === name : false,
				role: role ? plain(role.innerHTML) : null,
				roleTag: role ? role.tagName.toLowerCase() : null,
				imgCount: imgs.length,
				avatar: avatar
					? { src: avatar.getAttribute('src'), alt: avatar.getAttribute('alt'), width: avatar.getAttribute('width'), height: avatar.getAttribute('height'), first: byline.firstElementChild === avatar }
					: null,
				rating: rating
					? {
							tag: rating.tagName.toLowerCase(),
							role: rating.getAttribute('role'),
							label: rating.getAttribute('aria-label'),
							text: rating.textContent.trim(),
							stars: [...rating.children].map((s) => ({
								tag: s.tagName.toLowerCase(),
								star: s.classList.contains('acme-testimonial__star'),
								state: ['is-full', 'is-half', 'is-empty'].filter((c) => s.classList.contains(c)),
							})),
					  }
					: null,
				legacy: !!root.querySelector('.acme-testimonial__author, [data-rating]') || root.hasAttribute('data-rating'),
			};
		});
	}, html);
}

export function starStates(r) {
	return [1, 2, 3, 4, 5].map((i) => (r >= i ? 'is-full' : r >= i - 0.5 ? 'is-half' : 'is-empty'));
}

/** Assert that described markup matches the 4.x contract for an expected testimonial. */
export function assertV4(d, exp, label = '') {
	const m = (s) => `${label}: ${s}`;
	expect(d.tag, m('root element')).toBe('figure');
	expect(d.classes, m('root classes')).toContain('wp-block-acme-testimonial');
	for (const c of exp.classes) expect(d.classes, m(`class ${c}`)).toContain(c);
	expect(d.classes.includes('has-rating'), m('has-rating class')).toBe(exp.rating > 0);
	expect(d.legacy, m('legacy markup left')).toBe(false);
	expect(d.childTags[0], m('first child')).toBe('blockquote');
	expect(d.quote, m('quote')).toBe(exp.quote);
	expect(d.hasByline, m('figcaption.acme-testimonial__byline')).toBe(true);
	expect(d.nameTag, m('name element')).toBe('cite');
	expect(d.name, m('name')).toBe(exp.name);
	expect(d.nameIsFirstText, m('name comes first in the byline')).toBe(true);
	if (exp.role === null) {
		expect(d.role, m('no role element')).toBeNull();
	} else {
		expect(d.roleTag, m('role element')).toBe('span');
		expect(d.role, m('role')).toBe(exp.role);
	}
	if (exp.avatar) {
		expect(d.avatar, m('avatar in the byline')).not.toBeNull();
		expect(d.avatar.src, m('avatar src')).toContain(exp.avatar);
		expect(d.avatar.alt, m('avatar alt')).toBe('');
		expect([d.avatar.width, d.avatar.height], m('avatar size')).toEqual(['48', '48']);
		expect(d.avatar.first, m('avatar first in byline')).toBe(true);
		expect(d.imgCount, m('one image')).toBe(1);
	} else {
		expect(d.imgCount, m('no image')).toBe(0);
	}
	if (exp.rating > 0) {
		expect(d.rating, m('rating element')).not.toBeNull();
		expect(d.rating.tag).toBe('div');
		expect(d.rating.role, m('rating role')).toBe('img');
		expect(d.rating.label, m('rating label')).toBe(`Rated ${exp.rating} out of 5`);
		expect(d.rating.text, m('stars are elements, not text')).toBe('');
		expect(d.rating.stars.map((s) => s.tag + (s.star ? '.star' : '')), m('five star spans')).toEqual(Array(5).fill('span.star'));
		expect(d.rating.stars.map((s) => s.state.join(' ')), m('star states')).toEqual(starStates(exp.rating));
		expect(d.childTags[d.childTags.length - 1], m('rating is last')).toBe('div');
	} else {
		expect(d.rating, m('no rating element')).toBeNull();
	}
}

/** Testimonial blocks in the editor (flattened), with their saved HTML and attributes. */
export function editorTestimonials(page) {
	return page.evaluate(() => {
		const flat = (bs) => bs.flatMap((b) => [b, ...flat(b.innerBlocks)]);
		return flat(wp.data.select('core/block-editor').getBlocks())
			.filter((b) => b.name === 'acme/testimonial')
			.map((b) => ({ attributes: JSON.parse(JSON.stringify(b.attributes)), html: wp.blocks.getBlockContent(b) }));
	});
}

/** Serialize the editor's current blocks (what a save would write). */
export const serializeBlocks = (page) => page.evaluate(() => wp.blocks.serialize(wp.data.select('core/block-editor').getBlocks()));

/** JSON-LD Review items of a front-end page. */
export async function reviewsOf(page, url) {
	const html = await (await page.request.get(url)).text();
	const m = html.match(/<script[^>]*class="acme-testimonials-schema"[^>]*>([\s\S]*?)<\/script>/);
	expect(m, `JSON-LD missing on ${url}`).not.toBeNull();
	return { html, reviews: JSON.parse(m[1])['@graph'] };
}
