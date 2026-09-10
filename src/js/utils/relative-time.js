/*
 * Locale-aware relative timestamps (replaces the jquery-timeago plugin).
 *
 * - Locale resolution: document <html lang> first, then the admin script
 *   locale passed from PHP, then the runtime default.
 * - One Intl.RelativeTimeFormat instance per locale is memoized; each table
 *   row only calls format().
 * - Division thresholds mirror the previous timeago output (seconds → years
 *   with 4.34524-week months).
 * - Presentation is unchanged from the jquery-timeago UI: callers prepend a
 *   bold <strong><time class="timeago"> next to the original absolute date.
 */

const DIVISIONS = [
	{ amount: 60, unit: 'second' },
	{ amount: 60, unit: 'minute' },
	{ amount: 24, unit: 'hour' },
	{ amount: 7, unit: 'day' },
	{ amount: 4.34524, unit: 'week' },
	{ amount: 12, unit: 'month' },
	{ amount: Number.POSITIVE_INFINITY, unit: 'year' },
];

const formatters = {};

/**
 * Locale for relative strings: the document language, then the admin script locale.
 *
 * @return {string|undefined} BCP 47 tag, or undefined for the runtime default.
 */
function resolveLocale() {
	const admin = window[ 'wp-stream-admin' ];
	return (
		( document.documentElement && document.documentElement.lang ) ||
		( admin && admin.locale ) ||
		undefined
	);
}

/**
 * Memoized Intl.RelativeTimeFormat per locale (one per page, not one per row).
 *
 * @param {string|undefined} locale Locale tag.
 * @return {Intl.RelativeTimeFormat} Formatter.
 */
function getFormatter( locale ) {
	const key = locale || '';
	if ( ! formatters[ key ] ) {
		formatters[ key ] = new Intl.RelativeTimeFormat( locale, { numeric: 'auto' } );
	}
	return formatters[ key ];
}

/**
 * Format an ISO timestamp as a locale-aware relative string.
 *
 * @param {string} isoString Instant in ISO-8601 form.
 * @param {number} now       Comparison time in milliseconds.
 * @return {string} Relative time, or an empty string when the instant is invalid.
 */
export default function formatRelativeTime( isoString, now = Date.now() ) {
	const date = new Date( isoString );

	if ( Number.isNaN( date.getTime() ) ) {
		return '';
	}

	const formatter = getFormatter( resolveLocale() );
	let duration = ( date.getTime() - now ) / 1000;

	for ( let i = 0; i < DIVISIONS.length; i++ ) {
		const division = DIVISIONS[ i ];
		if ( Math.abs( duration ) < division.amount ) {
			return formatter.format( Math.round( duration ), division.unit );
		}
		duration /= division.amount;
	}

	return '';
}
