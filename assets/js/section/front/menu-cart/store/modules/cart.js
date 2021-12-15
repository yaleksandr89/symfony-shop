import axios from "axios";
import { StatusCodes } from "http-status-codes";
import { apiConfig, apiConfigPatch } from "../../../../../utils/settings";
import { concatUrlByParams } from "../../../../../utils/url-generator";
import { setCookie } from "../../../../../utils/cookie-manager";

const state = () => ({
  cart: {},
  isLoading: false,

  staticStore: {
    url: {
      apiCart: window.staticStore.urlCart,
      apiCartProduct: window.staticStore.urlCartProduct,
      viewProduct: window.staticStore.urlViewProduct,
      viewCart: window.staticStore.urlViewCart,
      assetImageProducts: window.staticStore.urlAssetImageProducts,
    },
    localization: window.staticStore.menu_cart_localization,
  },
});

const getters = {
  totalPrice(state) {
    let result = 0;
    if (!state.cart.cartProducts) {
      return 0;
    }

    state.cart.cartProducts.forEach((cartProduct) => {
      result += cartProduct.product.price * cartProduct.quantity;
    });

    return result;
  },
};

const actions = {
  async getCart({ state, commit, dispatch }) {
    const url = state.staticStore.url.apiCart;
    const result = await axios.get(url, apiConfig);

    if (
      result.data &&
      result.data["hydra:member"].length &&
      StatusCodes.OK === result.status
    ) {
      commit("setIsLoading", false);
      commit("setCart", result.data["hydra:member"][0]);
    }
  },
  async cleanCart({ state, commit }) {
    const url = concatUrlByParams(state.staticStore.url.apiCart, state.cart.id);
    const result = await axios.delete(url, apiConfig);

    if (StatusCodes.NO_CONTENT === result.status) {
      commit("setCart", {});
    }
  },
  async removeCartProduct({ state, commit, dispatch }, cartProductId) {
    const url = concatUrlByParams(
      state.staticStore.url.apiCartProduct,
      cartProductId
    );
    const result = await axios.delete(url, apiConfig);

    if (StatusCodes.NO_CONTENT === result.status) {
      dispatch("getCart");
    }
  },
  async addCartProduct({ state, commit, dispatch }, productData) {
    if (!state.cart.cartProducts) {
      await dispatch("createCart");
    }

    if (!productData.quantity) {
      productData.quantity = 1;
    }

    if (state.isLoading) {
      return;
    }

    const existCartProduct = state.cart.cartProducts.find(
      (cartProduct) => cartProduct.product.uuid === productData.uuid
    );

    commit("setIsLoading", true);

    if (existCartProduct) {
      let newQuantity = existCartProduct.quantity + productData.quantity;

      // если количество имеющегося продукта меньше, чем добавляемое значение
      // то позволяем добавить только максимальное значение
      if (existCartProduct.product.quantity <= newQuantity) {
        newQuantity = existCartProduct.product.quantity;
      }

      dispatch("addExistCartProduct", {
        cartProductId: existCartProduct.id,
        quantity: existCartProduct.quantity + productData.quantity,
      });
    } else {
      dispatch("addNewCartProduct", productData);
    }
  },
  async createCart({ state, dispatch }) {
    const url = state.staticStore.url.apiCart;
    const result = await axios.post(url, {}, apiConfig);

    if (result.data && StatusCodes.CREATED === result.status) {
      // устанавливаем срок жизни 1 день
      setCookie("CART_TOKEN", result.data.token, {
        secure: true,
        "max-age": 86400,
      });
      await dispatch("getCart");
    }
  },
  async addExistCartProduct({ state, dispatch }, cartProductData) {
    const url = concatUrlByParams(
      state.staticStore.url.apiCartProduct,
      cartProductData.cartProductId
    );
    const data = {
      quantity: cartProductData.quantity,
    };
    const result = await axios.patch(url, data, apiConfigPatch);

    if (StatusCodes.OK === result.status) {
      dispatch("getCart");
    }
  },
  async addNewCartProduct({ state, dispatch }, productData) {
    const url = state.staticStore.url.apiCartProduct;
    const data = {
      cart: "/api/carts/" + state.cart.id,
      product: "/api/products/" + productData.uuid,
      quantity: productData.quantity,
    };

    const result = await axios.post(url, data, apiConfig);
    if (result.data && StatusCodes.CREATED === result.status) {
      dispatch("getCart");
    }
  },
};

const mutations = {
  setCart(state, cart) {
    state.cart = cart;
  },
  setIsLoading(state, isLoading) {
    state.isLoading = isLoading;
  },
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations,
};
