/**
 * Event details form (dates are stored as UTC timestamps, edited in the site timezone).
 */
import { __ } from '@wordpress/i18n';
import {
	BaseControl,
	DateTimePicker,
	SelectControl,
	TextControl,
	ToggleControl,
	Button,
} from '@wordpress/components';
import { dateI18n, getDate } from '@wordpress/date';

const toPicker = ( timestamp ) =>
	timestamp ? dateI18n( 'Y-m-d\\TH:i:s', timestamp * 1000 ) : undefined;

const fromPicker = ( value ) =>
	value ? Math.floor( getDate( value ).getTime() / 1000 ) : 0;

export default function EventDetails( { meta, onChange } ) {
	const start = meta._acme_event_start || 0;
	const end = meta._acme_event_end || 0;
	const allDay = !! meta._acme_event_all_day;

	return (
		<>
			<BaseControl
				__nextHasNoMarginBottom
				id="acme-event-start"
				label={ __( 'Starts', 'acme-events-lite' ) }
			>
				<DateTimePicker
					currentDate={ toPicker( start ) }
					onChange={ ( value ) => {
						let ts = fromPicker( value );
						if ( allDay && ts ) {
							// All-day events start at local midnight.
							ts = fromPicker( dateI18n( 'Y-m-d\\T00:00:00', ts * 1000 ) );
						}
						onChange( { _acme_event_start: ts } );
					} }
					is12Hour={ false }
				/>
			</BaseControl>
			<BaseControl
				__nextHasNoMarginBottom
				id="acme-event-end"
				label={ __( 'Ends (optional)', 'acme-events-lite' ) }
				help={ __(
					'Without an end, the event lasts until the end of its day.',
					'acme-events-lite'
				) }
			>
				<DateTimePicker
					currentDate={ toPicker( end ) }
					onChange={ ( value ) =>
						onChange( { _acme_event_end: fromPicker( value ) } )
					}
					is12Hour={ false }
				/>
				{ !! end && (
					<Button
						variant="link"
						onClick={ () => onChange( { _acme_event_end: 0 } ) }
					>
						{ __( 'Remove end', 'acme-events-lite' ) }
					</Button>
				) }
			</BaseControl>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'All-day event', 'acme-events-lite' ) }
				checked={ allDay }
				onChange={ ( value ) => onChange( { _acme_event_all_day: value } ) }
			/>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Status', 'acme-events-lite' ) }
				value={
					meta._acme_event_status === 'canceled'
						? 'cancelled'
						: meta._acme_event_status || 'scheduled'
				}
				options={ [
					{ label: __( 'Scheduled', 'acme-events-lite' ), value: 'scheduled' },
					{ label: __( 'Postponed', 'acme-events-lite' ), value: 'postponed' },
					{ label: __( 'Cancelled', 'acme-events-lite' ), value: 'cancelled' },
				] }
				onChange={ ( value ) => onChange( { _acme_event_status: value } ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Venue', 'acme-events-lite' ) }
				value={ meta._acme_event_venue || '' }
				onChange={ ( value ) => onChange( { _acme_event_venue: value } ) }
			/>
		</>
	);
}
