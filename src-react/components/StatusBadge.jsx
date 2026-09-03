/**
 * A status pill.
 *
 * The consumer maps its own domain statuses onto the five tones; the package
 * does not know what "confirmed" means, only what "success" looks like.
 *
 * @param {Object} props
 * @param {string} props.children Translated status text.
 * @param {string} [props.tone]   success | warning | danger | info | neutral.
 */
export default function StatusBadge( { children, tone = 'neutral' } ) {
	return (
		<span className={ `mhmui-status mhmui-status--${ tone }` }>
			{ children }
		</span>
	);
}
