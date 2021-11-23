import axios from 'axios';
import {StatusCodes} from 'http-status-codes';
import {apiConfig, apiConfigPatch} from '../../../../../utils/settings';
import {concatUrlByParams} from '../../../../../utils/url-generator';

function getAlertStructure() {
    return {
        type: null,
        message: null,
    };
}

const state = () => ({
    cart: {},
    alert: getAlertStructure(),
    isSentForm: false,
    staticStore: {
        url: {
            apiCart: window.staticStore.urlCart,
            apiCartProduct: window.staticStore.urlCartProduct,
            apiOrder: window.staticStore.urlOrder,
            viewProduct: window.staticStore.urlViewProduct,
            loginPage: window.staticStore.urlLoginPage,
            assetImageProducts: window.staticStore.urlAssetImageProducts,
        },
        user: {
            isLoggedIn: window.staticStore.isUserLoggedIn
        },
        localization: window.staticStore.front_cart_localization,
    },
});

const getters = {
    totalPrice(state) {
        let result = 0;
        if (!state.cart.cartProducts) {
            return 0;
        }

        state.cart.cartProducts.forEach(
            cartProduct => {
                result += cartProduct.product.price * cartProduct.quantity;
            }
        );
        return result;
    }
};

const actions = {
    async getCart({state, commit}) {
        const url = state.staticStore.url.apiCart;
        const result = await axios.get(url, apiConfig);

        if (result.data && result.data["hydra:member"][0].cartProducts.length && StatusCodes.OK === result.status) {
            commit('setCart', result.data["hydra:member"][0]);
        } else {
            commit('setCart', {});
            commit('setAlert', {
                type: 'info',
                message: state.staticStore.localization.cart_empty
            });
        }
    },
    async cleanCart({state, commit}) {
        const url = concatUrlByParams(
            state.staticStore.url.apiCart,
            state.cart.id
        );
        const result = await axios.delete(url, apiConfig);

        if (StatusCodes.NO_CONTENT === result.status) {
            commit('setCart', {});
        }
    },
    async removeCartProduct({state, commit, dispatch}, cartProductId) {
        const url = concatUrlByParams(
            state.staticStore.url.apiCartProduct,
            cartProductId
        );
        const result = await axios.delete(url, apiConfig);

        if (StatusCodes.NO_CONTENT === result.status) {
            dispatch('getCart');
        }
    },
    async updateCartProductQuantity({state, dispatch}, payload) {
        const url = concatUrlByParams(
            state.staticStore.url.apiCartProduct,
            payload.cartProductId
        );
        const data = {
            quantity: parseInt(payload.quantity)
        };
        const result = await axios.patch(url, data, apiConfigPatch);

        if (StatusCodes.OK === result.status) {
            dispatch('getCart');
        }
    },
    async makeOrder({state, commit, dispatch}) {
        const url = state.staticStore.url.apiOrder;
        const data = {
            cartId: state.cart.id,
        };
        const result = await axios.post(url, data, apiConfig);

        if (result.data && StatusCodes.CREATED === result.status) {
            commit('setAlert', {
                type: 'success',
                message: 'Thank you for your purchase! Our manager will contact with you in 24 hours.'
            });
            commit('setIsSentForm', true);
            dispatch('cleanCart');
        }
    },
};

const mutations = {
    setCart(state, cart) {
        state.cart = cart;
    },
    setAlert(state, model) {
        state.alert = {
            type: model.type,
            message: model.message,
        }
    },
    cleanAlert(state) {
        state.alert = getAlertStructure();
    },
    setIsSentForm(state, value) {
        state.isSentForm = value;
    },
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};