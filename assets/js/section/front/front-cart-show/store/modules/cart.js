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

function getAlertStructure() {
  return {
    type: null,
    message: null,
  };
}

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
      isLoggedIn: window.staticStore.isUserLoggedIn,
    },
    localization: window.staticStore.front_cart_localization,
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
  async getCart({ state, commit }, { force = false } = {}) {
    const cart = await cartSync.load(() => loadCart(state), { force });

    if (!Object.keys(cart).length) {
      commit("setAlert", {
        type: "info",
        message: state.staticStore.localization.cart_empty,
      });
    }

    return cart;
  },
  async cleanCart({ state }) {
    const url = concatUrlByParams(state.staticStore.url.apiCart, state.cart.id);

    const result = await axios.delete(url, apiConfig);

    if (StatusCodes.NO_CONTENT === result.status) {
      cartSync.publish({});
      setCookie("CART_TOKEN", result.data.token, {
        secure: true,
        "max-age": 0,
      });
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
  async updateCartProductQuantity({ state, dispatch }, payload) {
    const quantity = Number(payload.quantity);
    const stock = Number(payload.stock);
    if (
      !Number.isInteger(quantity) ||
      quantity < 1 ||
      !Number.isInteger(stock) ||
      stock < 1 ||
      quantity > stock
    ) {
      return false;
    }

    const url = concatUrlByParams(
      state.staticStore.url.apiCartProduct,
      payload.cartProductId
    );
    const data = {
      quantity,
    };
    try {
      const result = await axios.patch(url, data, apiConfigPatch);

      if (StatusCodes.OK === result.status) {
        await dispatch("getCart", { force: true });
        return true;
      }
    } catch (error) {
      try {
        await dispatch("getCart", { force: true });
      } catch (refreshError) {
        // Preserve the write error for callers instead of reporting a false success.
      }

      throw error;
    }

    return false;
  },
  async makeOrder({ state, commit }) {
    const url = state.staticStore.url.apiOrder;
    const data = {
      cartId: state.cart.id,
    };
    const result = await axios.post(url, data, apiConfig);

    if (result.data && StatusCodes.CREATED === result.status) {
      commit("setAlert", {
        type: "success",
        message:
          "Thank you for your purchase! Our manager will contact with you in 24 hours.",
      });
      commit("setIsSentForm", true);
      cartSync.publish({});
      setCookie("CART_TOKEN", "", {
        secure: true,
        path: "/",
        "max-age": 0,
      });
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
    };
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
