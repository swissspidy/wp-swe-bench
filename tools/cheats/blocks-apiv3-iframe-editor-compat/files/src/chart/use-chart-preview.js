/**
 * Draw the chart preview in the editor canvas with the shared renderer.
 *
 * The canvas is an iframe: the element is looked up through a ref (never through
 * the global `document`), and size changes are observed on the element itself
 * with the ResizeObserver of the window it lives in (the parent window's
 * `resize` event doesn't fire when only the canvas changes size, e.g. in the
 * tablet/mobile previews).
 */
import { useEffect, useRef } from '@wordpress/element';

/**
 * @param {Object} config Chart configuration ({ series, height, showValues }).
 * @return {Object} Ref for the `.acme-chart__canvas` element.
 */
export default function useChartPreview( config ) {
	const canvasRef = useRef();
	const { series, height, showValues } = config;

	useEffect( () => {
		const canvas = canvasRef.current;
		if ( ! canvas || ! series.length || ! window.AcmeCharts ) {
			return;
		}

		const redraw = () => window.AcmeCharts.draw( canvas, { series, height, showValues } );
		redraw();

		const view = canvas.ownerDocument.defaultView;
		if ( ! view || ! view.ResizeObserver ) {
			return;
		}
		let lastWidth = canvas.clientWidth;
		const observer = new view.ResizeObserver( () => {
			if ( canvas.clientWidth !== lastWidth ) {
				lastWidth = canvas.clientWidth;
				redraw();
			}
		} );
		observer.observe( canvas );
		return () => observer.disconnect();
	}, [ series, height, showValues ] );

	return canvasRef;
}
