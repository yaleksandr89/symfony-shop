import Vue from 'vue';
import App from './App';
import store from './store';

if (document.getElementById('appFrontMenuCart')) {
    new Vue({
        el: '#appFrontMenuCart',
        store,
        render: h => h(App),
    });
}