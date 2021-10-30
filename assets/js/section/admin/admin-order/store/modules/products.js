import {concatUrlByParams, getUrlProductsByCategory} from '../../../../../utils/url-generator';
import axios from 'axios';
import {StatusCodes} from 'http-status-codes';
import {apiConfig} from '../../../../../utils/settings';

const state = () => ({
    categories: [],
    categoryProducts: [],
    orderProducts: [],
    busyProductsIds: [],

    newOrderProduct: {
        categoryId: '',
        productId: '',
        quantity: '',
        pricePerOne: '',
    },
    staticStore: {
        orderId: window.staticStore.orderId,

        url: {
            viewProduct: window.staticStore.urlViewProduct,
            apiOrder: window.staticStore.urlApiOrder,
            apiOrderProduct: window.staticStore.urlApiOrderProduct,
            apiCategory: window.staticStore.urlApiCategory,
            apiProduct: window.staticStore.urlApiProduct,
        },
    },
    itemsPerPage: 25
});

const getters = {
    freeCategoryProducts(state) {
        return state.categoryProducts.filter(
            item => state.busyProductsIds.indexOf(item.id) === -1
        );
    },
};

const actions = {
    async getOrderProducts({commit, state}) {
        const url = concatUrlByParams(
            state.staticStore.url.apiOrder,
            state.staticStore.orderId
        );
        const result = await axios.get(url, apiConfig);

        if (result.data && StatusCodes.OK === result.status) {
            commit('setOrderProducts', result.data.orderProducts);
            commit('setBusyProductsIds');
        }
    },
    async getProductsByCategory({commit, state}) {
        const url = getUrlProductsByCategory(
            state.staticStore.url.apiProduct,
            state.newOrderProduct.categoryId,
            1,
            state.itemsPerPage
        );
        const result = await axios.get(url, apiConfig);

        if (result.data && StatusCodes.OK === result.status) {
            commit('setCategoryProducts', result.data['hydra:member']);
        }
    },
    async getCategories({commit, state}) {
        const url = state.staticStore.url.apiCategory;
        const result = await axios.get(url, apiConfig);

        if (result.data && StatusCodes.OK === result.status) {
            commit('setCategories', result.data['hydra:member']);
        }
    },
    async addNewOrderProduct({state, dispatch}) {
        const url = state.staticStore.url.apiOrderProduct;
        const data = {
            pricePerOne: state.newOrderProduct.pricePerOne.toString(),
            quantity: parseInt(state.newOrderProduct.quantity),
            product: '/api/products/' + state.newOrderProduct.productId,
            appOrder: '/api/orders/' + state.staticStore.orderId,
        };
        const result = await axios.post(url, data, apiConfig);

        if (result.data && StatusCodes.CREATED === result.status) {
            dispatch('getOrderProducts');
        }
    },
    async removeOrderProduct({state, dispatch}, orderProductId) {
        const url = concatUrlByParams(
            state.staticStore.url.apiOrderProduct,
            orderProductId
        );
        const result = await axios.delete(url, apiConfig);

        if (StatusCodes.NO_CONTENT === result.status) {
            dispatch('getOrderProducts');
        }
    }
};

const mutations = {
    setCategories(state, categories) {
        state.categories = categories;
    },
    setCategoryProducts(state, categoryProducts) {
        state.categoryProducts = categoryProducts;
    },
    setNewProductInfo(state, formData) {
        state.newOrderProduct.categoryId = formData.categoryId;
        state.newOrderProduct.productId = formData.productId;
        state.newOrderProduct.quantity = formData.quantity;
        state.newOrderProduct.pricePerOne = formData.pricePerOne;
    },
    setOrderProducts(state, orderProducts) {
        state.orderProducts = orderProducts;
    },
    setBusyProductsIds(state) {
        state.busyProductsIds = state.orderProducts.map(item => item.product.id);
    },
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};