import axios from 'axios';
import {StatusCodes} from 'http-status-codes';
import {apiConfig, apiConfigPatch} from '../../../../../utils/settings';
import {concatUrlByParams} from "../../../../../utils/url-generator";

const state = () => ({
    cart: {},
    staticStore: {
        url: {
            apiCart: window.staticStore.urlCart,
            apiCartProduct: window.staticStore.urlCartProduct,
            viewProduct: window.staticStore.urlViewProduct,
            assetImageProducts: window.staticStore.urlAssetImageProducts,
        },
    },
});

const getters = {};

const actions = {
    async getCart({state, commit}) {
        const url = state.staticStore.url.apiCart;
        const result = await axios.get(url, apiConfig);

        if (result.data && StatusCodes.OK === result.status) {
            commit('setCart', result.data["hydra:member"][0]);
        }
    },
    async removeCartProduct({state, dispatch}, cartProductId) {
        const url = concatUrlByParams(state.staticStore.url.apiCartProduct, cartProductId);
        const result = await axios.delete(url, apiConfig);

        if (StatusCodes.NO_CONTENT === result.status) {
            dispatch('getCart');
        }
    },
    async updateCartProductQuantity({state, dispatch}, payload) {
        const url = concatUrlByParams(state.staticStore.url.apiCartProduct, payload.cartProductId);
        const data = {
            quantity: parseInt(payload.quantity)
        };
        const result = await axios.patch(url, data, apiConfigPatch);

        if (StatusCodes.OK === result.status) {
            dispatch('getCart');
        }
    },
};

const mutations = {
    setCart(state, cart) {
        state.cart = cart;
    },
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};