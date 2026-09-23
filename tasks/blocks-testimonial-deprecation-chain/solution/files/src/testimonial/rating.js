/**
 * Star rating markup (4.x): shared by save() and the editor preview.
 */
import { formatRating, starStates } from './utils';

export default function Rating( { rating } ) {
	return (
		// Not translatable: save() output must not depend on the editor language.
		<div
			className="acme-testimonial__rating"
			role="img"
			aria-label={ `Rated ${ formatRating( rating ) } out of 5` }
		>
			{ starStates( rating ).map( ( state, index ) => (
				<span key={ index } className={ `acme-testimonial__star is-${ state }` } />
			) ) }
		</div>
	);
}
