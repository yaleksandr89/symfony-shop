const $ = require('jquery');
require('bootstrap');

global.$ = global.jQuery = $;

require('jquery.easing');
require('chart.js');

require('./js/section/admin/theme/sb-admin-2');
require('./js/utils/changed-locale');
require('./js/section/admin/theme/filters-feature');

import './css/section/admin/libs.scss';
import './css/section/admin/sb-admin-2.css';
import './css/section/admin/styles.css';
import './css/section/admin/main.scss';
