import { useState } from 'react';
import { Pagination } from '@mhm/ui-core';

const LABELS = { previous: 'Previous', next: 'Next', of: 'of', navigation: 'Booking pages', page: 'Page' };

/** Inline pager under a list. */
export const Inline = () => {
	const [ page, setPage ] = useState( 2 );
	return <Pagination page={ page } totalPages={ 5 } onChange={ setPage } labels={ LABELS } />;
};

/** Footer variant: the bottom bar of a table card, range on the left. */
export const Footer = () => {
	const [ page, setPage ] = useState( 1 );
	return <Pagination variant="footer" page={ page } totalPages={ 12 } onChange={ setPage } labels={ LABELS } summary="1–20 of 236" />;
};
