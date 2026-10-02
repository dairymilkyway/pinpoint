import $ from 'jquery';

// DataTables and the yajra server-side button definitions resolve jQuery from
// the global scope, so it has to be published before those modules evaluate.
window.$ = window.jQuery = $;

import 'bootstrap';

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
