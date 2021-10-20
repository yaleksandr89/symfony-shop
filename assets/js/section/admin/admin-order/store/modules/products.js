import {concatUrlByParams, getUrlProductsByCategory} from "../../../../../utils/url-generator";
import axios from "axios";
import {StatusCodes} from "http-status-codes";
import {apiConfig} from "../../../../../utils/settings";

const state = () => ({
    categories: [],
    categoryProducts: [],
    newOrderProduct: {
        categoryId: "",
        productId: "",
        quantity: "",
        pricePerOne: "",
    },
    staticStore: {
        orderId: window.staticStore.orderId,
        orderProducts: window.staticStore.orderProducts,

        url: {
            viewProduct: window.staticStore.urlViewProduct,
            apiOrderProduct: window.staticStore.urlApiProductOrder,
            apiCategory: window.staticStore.urlApiCategory,
            apiProduct: window.staticStore.urlApiProduct,
        },
    },
    itemsPerPage: 25
});

const getters = {};

const actions = {
    async getProductsByCategory({commit, state}) {
        const url = getUrlProductsByCategory(
            state.staticStore.url.apiProduct,
            state.newOrderProduct.categoryId,
            1,
            state.itemsPerPage
        );

        const result = await axios.get(url, apiConfig);
        if (result.data && StatusCodes.OK === result.status) {
            commit("getCategoryProducts", result.data["hydra:member"]);
        }
    },
    async getCategories({commit, state}) {
        const url = state.staticStore.url.apiCategory;

        const result = await axios.get(url, apiConfig);
        if (result.data && StatusCodes.OK === result.status) {
            commit("getCategories", result.data["hydra:member"]);
        }
    },
    async addNewOrderProduct({state, dispatch}) {
        const url = state.staticStore.url.apiOrderProduct;
        const data = {
            pricePerOne: state.newOrderProduct.pricePerOne,
            quantity: parseInt(state.newOrderProduct.quantity),
            product: "/api/products/" + state.newOrderProduct.productId,
            appOrder: "/api/orders/" + state.staticStore.orderId,
        };

        const result = await axios.post(url, data, apiConfig);
        if (result.data && StatusCodes.CREATED === result.status) {
            console.log('ADDED!!!')
        }
    },
    async removeOrderProduct({state, dispatch}, orderProductId) {
        const url = concatUrlByParams(
            state.staticStore.url.apiOrderProduct,
            orderProductId
        );
        const result = await axios.delete(url, apiConfig);

        if (StatusCodes.NO_CONTENT === result.status) {
            console.log('Deleted success!!!');
        }
    }
};

const mutations = {
    getCategories(state, categories) {
        state.categories = categories;
    },
    getCategoryProducts(state, categoryProducts) {
        state.categoryProducts = categoryProducts;
    },
    setNewProductInfo(state, formData) {
        state.newOrderProduct.categoryId = formData.categoryId;
        state.newOrderProduct.productId = formData.productId;
        state.newOrderProduct.quantity = formData.quantity;
        state.newOrderProduct.pricePerOne = formData.pricePerOne;
    },
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};