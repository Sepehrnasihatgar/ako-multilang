/**
 * REST client for cedar/v1. Authentication (cookie + nonce) is handled by
 * `@wordpress/api-fetch`, which WordPress preconfigures in the admin.
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const BASE = '/cedar/v1';

export const getLinks = ( query ) =>
	apiFetch( { path: addQueryArgs( `${ BASE }/links`, query ) } );

export const getCandidates = ( query ) =>
	apiFetch( { path: addQueryArgs( `${ BASE }/links/candidates`, query ) } );

export const linkPost = ( id, target, language ) =>
	apiFetch( {
		path: `${ BASE }/links/${ id }`,
		method: 'PUT',
		data: { target, language },
	} );

export const unlinkPost = ( id, scope = 'post' ) =>
	apiFetch( {
		path: addQueryArgs( `${ BASE }/links/${ id }`, { scope } ),
		method: 'DELETE',
	} );

export const getDetail = ( id ) =>
	apiFetch( { path: `${ BASE }/links/${ id }` } );

export const setPostLanguage = ( id, language ) =>
	apiFetch( {
		path: `${ BASE }/links/${ id }/language`,
		method: 'PUT',
		data: { language },
	} );

export const duplicatePost = ( id, language ) =>
	apiFetch( {
		path: `${ BASE }/links/${ id }/duplicate`,
		method: 'POST',
		data: { language },
	} );

export const getLanguages = () => apiFetch( { path: `${ BASE }/languages` } );

export const getCatalog = () =>
	apiFetch( { path: `${ BASE }/languages/catalog` } );

export const addLanguage = ( data ) =>
	apiFetch( { path: `${ BASE }/languages`, method: 'POST', data } );

export const updateLanguage = ( code, data ) =>
	apiFetch( { path: `${ BASE }/languages/${ code }`, method: 'PUT', data } );

export const removeLanguage = ( code ) =>
	apiFetch( { path: `${ BASE }/languages/${ code }`, method: 'DELETE' } );

export const reorderLanguages = ( order ) =>
	apiFetch( {
		path: `${ BASE }/languages/order`,
		method: 'PUT',
		data: { order },
	} );

/**
 * A readable message from a failed request.
 *
 * @param {Object} error    apiFetch error.
 * @param {string} fallback Message when the server sent none.
 * @return {string} Message.
 */
export const errorMessage = ( error, fallback ) =>
	( error && error.message ) || fallback;

export const getSetup = () => apiFetch( { path: `${ BASE }/setup` } );

export const saveSetupLanguages = ( defaultLocale, locales ) =>
	apiFetch( {
		path: `${ BASE }/setup/languages`,
		method: 'PUT',
		data: { default: defaultLocale, languages: locales },
	} );

export const getSuggestions = () =>
	apiFetch( { path: `${ BASE }/setup/suggestions` } );

export const connectPairs = ( pairs ) =>
	apiFetch( {
		path: `${ BASE }/setup/pairs`,
		method: 'POST',
		data: { pairs },
	} );

export const placeSwitcher = ( look, placement ) =>
	apiFetch( {
		path: `${ BASE }/setup/switcher`,
		method: 'POST',
		data: { look, placement },
	} );

export const getSetupSummary = () =>
	apiFetch( { path: `${ BASE }/setup/summary` } );

export const completeSetup = () =>
	apiFetch( { path: `${ BASE }/setup/complete`, method: 'POST' } );

export const getImportSources = () => apiFetch( { path: `${ BASE }/import` } );

export const importBatch = ( source, offset, limit = 25 ) =>
	apiFetch( {
		path: `${ BASE }/import/${ source }`,
		method: 'POST',
		data: { offset, limit },
	} );
