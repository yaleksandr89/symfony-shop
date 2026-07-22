import axios from "axios";
import { StatusCodes } from "http-status-codes";
import { apiConfig, apiConfigPatch } from "../../../../../utils/settings";
import { concatUrlByParams } from "../../../../../utils/url-generator";
import { setCookie } from "../../../../../utils/cookie-manager";
import {
  formatCents,
  sumCartProductsToCents,
} from "../../../../../utils/money";
import cartSync from "../../../cart-sync";

async function loadCart(state) {
  const result = await axios.get(state.staticStore.url.apiCart, apiConfig);

  if (
    result.data &&
    result.data["hydra:member"].length &&
    StatusCodes.OK === result.status
  ) {
    return result.data["hydra:member"][0];
  }

  return {};
}

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
    if (!state.cart.cartProducts) {
      return formatCents(0);
    }

    return formatCents(sumCartProductsToCents(state.cart.cartProducts));
  },
};

const actions = {
  async getCart({ state }, { force = false } = {}) {
    return cartSync.load(() => loadCart(state), { force });
  },
  async cleanCart({ state }) {
    const url = concatUrlByParams(state.staticStore.url.apiCart, state.cart.id);
    const result = await axios.delete(url, apiConfig);

    if (StatusCodes.NO_CONTENT === result.status) {
      cartSync.publish({});
    }
  },
  async removeCartProduct({ state, dispatch }, cartProductId) {
    const url = concatUrlByParams(
      state.staticStore.url.apiCartProduct,
      cartProductId
    );
    const result = await axios.delete(url, apiConfig);

    if (StatusCodes.NO_CONTENT === result.status) {
      await dispatch("getCart", { force: true });
    }
  },
  async addCartProduct({ state, commit, dispatch }, productData) {
    if (state.isLoading) {
      return;
    }

    commit("setIsLoading", true);

    try {
      const amount = Number(
        typeof productData.quantity === "undefined" ? 1 : productData.quantity
      );

      if (!Number.isInteger(amount) || amount < 1) {
        return;
      }

      if (!state.cart.cartProducts) {
        await dispatch("createCart");
      }

      const existCartProduct = state.cart.cartProducts.find(
        (cartProduct) => cartProduct.product.uuid === productData.uuid
      );

      if (existCartProduct) {
        const stock = Number(existCartProduct.product.quantity);
        const currentQuantity = Number(existCartProduct.quantity);

        if (
          !Number.isInteger(stock) ||
          stock < 1 ||
          !Number.isInteger(currentQuantity)
        ) {
          return;
        }

        const newQuantity = Math.min(currentQuantity + amount, stock);
        if (newQuantity === currentQuantity) {
          return;
        }

        await dispatch("addExistCartProduct", {
          cartProductId: existCartProduct.id,
          quantity: newQuantity,
        });
      } else {
        const stock = Number(productData.stock);
        if (!Number.isInteger(stock) || stock < 1) {
          return;
        }

        await dispatch("addNewCartProduct", {
          uuid: productData.uuid,
          quantity: Math.min(amount, stock),
        });
      }
    } catch (error) {
      try {
        await dispatch("getCart", { force: true });
      } catch (refreshError) {
        // Preserve the write error for callers instead of reporting a false success.
      }

      throw error;
    } finally {
      commit("setIsLoading", false);
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
      await dispatch("getCart", { force: true });
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
      return dispatch("getCart", { force: true });
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
      return dispatch("getCart", { force: true });
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
