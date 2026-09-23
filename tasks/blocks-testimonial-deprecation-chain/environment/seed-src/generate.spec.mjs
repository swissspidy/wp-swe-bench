// Authoring tool (not part of the image): regenerates environment/seed/posts/*.html.
//
// Runs in the block editor of a container with Acme Testimonials 3.2 active. The 1.x and 2.x
// markup is produced by registering the historical block definitions (their real save()
// functions, as shipped in 1.0 and 2.1) in place of the current one; 3.x markup comes from
// the current block. Avatar placeholders (999999 / AVATAR_URL_PLACEHOLDER) are replaced by
// seed.sh with the real attachment.
//
//   ln -s /opt/wpsb/node/node_modules <dir>/node_modules; cp -R lib <dir>/wpsb
//   WPSB_SPEC_DIR=<dir>/seed-src npx playwright test --config <dir>/wpsb/e2e/playwright.config.mjs
import { test } from '@playwright/test';
import fs from 'node:fs';
import { login, newPost } from '../wpsb/e2e/helpers.mjs';

test('generate seed markup', async ({ page }) => {
	await login(page);
	await newPost(page);
	const out = await page.evaluate(() => {
		const { createBlock, serialize, getBlockType, unregisterBlockType, registerBlockType } = wp.blocks;
		const { RichText, useBlockProps } = wp.blockEditor;
		const el = wp.element.createElement;
		const NAME = 'acme/testimonial';
		const current = getBlockType(NAME);
		const base = { title: 'Testimonial', category: 'text', edit: () => null };

		// ---- 1.0 (block API v1) ----
		const v1 = {
			...base,
			apiVersion: 1,
			attributes: {
				quote: { type: 'string', source: 'html', selector: '.acme-testimonial__quote' },
				author: { type: 'string', source: 'html', selector: '.acme-testimonial__author' },
			},
			supports: { html: false },
			save: ({ attributes }) =>
				el('blockquote', { className: 'acme-testimonial' },
					el(RichText.Content, { tagName: 'p', className: 'acme-testimonial__quote', value: attributes.quote }),
					el(RichText.Content, { tagName: 'cite', className: 'acme-testimonial__author', value: attributes.author })
				),
		};

		// ---- 2.1 (block API v2) ----
		const v2 = {
			...base,
			apiVersion: 2,
			attributes: {
				quote: { type: 'string', source: 'html', selector: '.acme-testimonial__quote p' },
				authorName: { type: 'string', source: 'html', selector: '.acme-testimonial__name' },
				authorRole: { type: 'string', source: 'html', selector: '.acme-testimonial__role' },
				rating: { type: 'string', default: '' },
			},
			supports: { html: false, align: ['left', 'right', 'wide'] },
			save: ({ attributes }) => {
				const { quote, authorName, authorRole, rating } = attributes;
				const count = Math.round(parseFloat(rating) || 0);
				return el('figure', useBlockProps.save(),
					el('blockquote', { className: 'acme-testimonial__quote' }, el(RichText.Content, { tagName: 'p', value: quote })),
					el('figcaption', { className: 'acme-testimonial__byline' },
						el(RichText.Content, { tagName: 'span', className: 'acme-testimonial__name', value: authorName }),
						authorRole && el(RichText.Content, { tagName: 'span', className: 'acme-testimonial__role', value: authorRole })
					),
					rating && el('div', { className: 'acme-testimonial__rating', 'data-rating': rating }, '★'.repeat(count) + '☆'.repeat(Math.max(0, 5 - count)))
				);
			},
		};

		const withVersion = (def, fn) => {
			unregisterBlockType(NAME);
			registerBlockType(NAME, def);
			try {
				return fn();
			} finally {
				unregisterBlockType(NAME);
				registerBlockType(NAME, current);
			}
		};
		const t = (attrs) => createBlock(NAME, attrs);
		const p = (content) => createBlock('core/paragraph', { content });
		const res = {};

		// 3.x first, with the current (registered) block.
		res['v3-testimonials'] = serialize([
			t({ quote: 'Rock solid for three years now.', authorName: 'Jane Doe', authorRole: 'CTO, Initech', rating: 5, avatarId: 999999, avatarUrl: 'AVATAR_URL_PLACEHOLDER', align: 'right', className: 'is-style-card' }),
			t({ quote: 'Does what it says on the tin.', authorName: 'Kim Nguyen' }),
			t({ quote: 'Support could be faster, the product is great.', authorName: 'Luis Romero', authorRole: 'IT manager', rating: 3 }),
		]);

		const v3Block = serialize([t({ quote: 'Too expensive for small teams.', authorName: 'Pat Doe', rating: 2 })]);

		res['v1-testimonials'] = [
			serialize([p('A few words from the customers of our very first release.')]),
			withVersion(v1, () => serialize([
				t({ quote: 'Acme Suite saved us <strong>two days a week</strong>. See <a href="https://example.org/case-study">our case study</a>.', author: 'Jane Doe, CTO at Initech' }),
				t({ quote: 'Simply the best.', author: 'Anonymous', className: 'featured-quote' }),
				t({ quote: 'Fast, friendly and fair.<br>We will be back.', author: 'Bob Smith, Head of Sales, EMEA' }),
			])),
		].join('\n\n');

		res['v2-testimonials'] = withVersion(v2, () => serialize([
			t({ quote: 'Our onboarding time dropped by half.', authorName: 'Maria Garcia', authorRole: 'HR Director, Globex', rating: '4', align: 'wide' }),
			t({ quote: 'Great value for money.', authorName: 'Tom Becker', authorRole: 'Owner, Becker &amp; Sons', rating: '4.5' }),
			t({ quote: 'I <em>love</em> the <a href="https://example.org/reports">reports</a>.', authorName: 'Priya Natarajan', className: 'is-style-card' }),
			t({ quote: 'Not for us, but well made.', authorName: 'Sam Lee', authorRole: 'Student', rating: '0' }),
		]));

		// Mixed page: a 1.0 testimonial in a group, a 2.x and a 3.x one side by side in columns.
		const v1Group = withVersion(v1, () => serialize([
			createBlock('core/group', {}, [t({ quote: 'We migrated 40 sites in a weekend.', author: 'Alex Chen, Lead developer, Umbrella Corp' })]),
		]));
		const v2Block = withVersion(v2, () => serialize([t({ quote: 'Five stars, no notes.', authorName: 'Grace Hopper', authorRole: 'Admiral', rating: '5' })]));
		const columns = serialize([
			createBlock('core/columns', {}, [
				createBlock('core/column', {}, [p('PLACEHOLDER_V2')]),
				createBlock('core/column', {}, [p('PLACEHOLDER_V3')]),
			]),
		]);
		const para = (x) => `<!-- wp:paragraph -->\n<p>${x}</p>\n<!-- /wp:paragraph -->`;
		if (!columns.includes(para('PLACEHOLDER_V2'))) throw new Error('unexpected columns markup');
		res['mixed-testimonials'] = [
			v1Group,
			columns.replace(para('PLACEHOLDER_V2'), v2Block).replace(para('PLACEHOLDER_V3'), v3Block),
		].join('\n\n');
		return res;
	});
	fs.mkdirSync('/tmp/gen-out', { recursive: true });
	for (const [k, v] of Object.entries(out)) fs.writeFileSync(`/tmp/gen-out/${k}.html`, v + '\n');
});
