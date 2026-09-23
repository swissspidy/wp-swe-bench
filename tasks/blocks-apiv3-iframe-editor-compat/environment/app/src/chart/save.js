/**
 * Saved markup (1.3+).
 *
 * The chart itself is drawn by assets/js/chart-renderer.js from the JSON in
 * `data-chart`; the table is the accessible fallback.
 */
import { RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { title, series, height, showValues } = attributes;

	return (
		<figure
			className="acme-chart"
			data-chart={ JSON.stringify( { series, height, showValues } ) }
		>
			<div
				className="acme-chart__canvas"
				style={ { height: `${ height }px` } }
				aria-hidden="true"
			/>
			{ ! RichText.isEmpty( title ) && (
				<RichText.Content
					tagName="figcaption"
					className="acme-chart__title"
					value={ title }
				/>
			) }
			<table className="acme-chart__table">
				<tbody>
					{ series.map( ( point, index ) => (
						<tr key={ index }>
							<th scope="row">{ point.label }</th>
							<td>{ point.value }</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</figure>
	);
}
