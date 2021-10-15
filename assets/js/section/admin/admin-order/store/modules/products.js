import {concatUrlByParams} from "../../../../../utils/url-generator";
import axios from "axios";
import {StatusCodes} from "http-status-codes";
import {apiConfig} from "../../../../../utils/settings";

const state = () => ({
    categories: [],
    staticStore: {
        orderId: window.staticStore.orderId,
        orderProducts: window.staticStore.orderProducts,

        url: {
            viewProduct: window.staticStore.urlViewProduct,
            apiOrderProduct: window.staticStore.urlApiProductOrder,
        }
    },
});

const getters = {};

const actions = {
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

const mutations = {};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};