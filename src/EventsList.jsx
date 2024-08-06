import { __experimentalSpacer as Spacer } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { clsx } from 'clsx';
import { Event } from './components/Event';
import { EventsPagination } from './components/EventsPagination';

/**
 * Renders a list of events.
 *
 * @param {Object} props
 */
export function EventsList( props ) {
	const {
		page,
		setPage,
		events,
		eventsIsLoading,
		eventsMeta,
		mapsApiKey,
		hasExtendedSettingsAddOn,
	} = props;
	const totalPages = eventsMeta.totalPages;

	const ulClasses = clsx( {
		SimpleHistoryLogitems: true,
		'is-loading': eventsIsLoading,
	} );

	return (
		<div style={ { backgroundColor: 'white', minHeight: '300px' } }>
			{ eventsIsLoading === false && events.length === 0 && (
				<p>
					{ __(
						'Your search did not match any history events.',
						'simple-history'
					) }
				</p>
			) }

			<ul className={ ulClasses }>
				{ events.map( ( event ) => (
					<Event
						key={ event.id }
						event={ event }
						mapsApiKey={ mapsApiKey }
						hasExtendedSettingsAddOn={ hasExtendedSettingsAddOn }
					/>
				) ) }
			</ul>

			<Spacer margin={ 4 } />

			<EventsPagination
				page={ page }
				totalPages={ totalPages }
				onClickPrev={ () => setPage( page - 1 ) }
				onClickNext={ () => setPage( page + 1 ) }
				onChangePage={ ( newPage ) =>
					setPage( parseInt( newPage, 10 ) )
				}
			/>

			<Spacer paddingBottom={ 4 } />
		</div>
	);
}
