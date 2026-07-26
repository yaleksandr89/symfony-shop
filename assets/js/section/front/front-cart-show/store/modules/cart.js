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

function normalizeCartProductId(value) {
  if (Number.isInteger(value) && value > 0) {
    return String(value);
  }

  if (typeof value === "string" && /^\d+$/.test(value)) {
    const normalizedValue = Number(value);

    if (Number.isSafeInteger(normalizedValue) && normalizedValue > 0) {
      return String(normalizedValue);
    }
  }

  return null;
}

function getUnavailableItemsByCartProductId(error, cart) {
  const response = error && error.response;
  const headers = response && response.headers;
  const contentType =
    headers && (headers["content-type"] || headers["Content-Type"]);
  const data = response && response.data;

  if (
    !response ||
    response.status !== StatusCodes.CONFLICT ||
    typeof contentType !== "string" ||
    !contentType.toLowerCase().startsWith("application/problem+json") ||
    !data ||
    typeof data !== "object" ||
    Array.isArray(data) ||
    data.type !== "/problems/cart-products-unavailable" ||
    data.status !== StatusCodes.CONFLICT ||
    !Array.isArray(data.unavailableItems) ||
    !data.unavailableItems.length
  ) {
    return null;
  }

  const cartProductIds = new Set(
    (cart && Array.isArray(cart.cartProducts) ? cart.cartProducts : [])
      .map((cartProduct) => normalizeCartProductId(cartProduct.id))
      .filter(Boolean)
  );
  const unavailableItemsByCartProductId = {};

  for (const item of data.unavailableItems) {
    if (
      !item ||
      typeof item !== "object" ||
      Array.isArray(item) ||
      !Number.isInteger(item.cartProductId) ||
      item.cartProductId <= 0 ||
      !["deleted", "unpublished"].includes(item.reason)
    ) {
      return null;
    }

    const cartProductId = normalizeCartProductId(item.cartProductId);
    if (
      !cartProductId ||
      !cartProductIds.has(cartProductId) ||
      Object.prototype.hasOwnProperty.call(
        unavailableItemsByCartProductId,
        cartProductId
      )
    ) {
      return null;
    }

    unavailableItemsByCartProductId[cartProductId] = item.reason;
  }

  return unavailableItemsByCartProductId;
}

function reconcileUnavailableItems(unavailableItemsByCartProductId, cart) {
  if (!cart || !Array.isArray(cart.cartProducts)) {
    return {};
  }

  const cartProductIds = new Set(
    cart.cartProducts
      .map((cartProduct) => normalizeCartProductId(cartProduct.id))
      .filter(Boolean)
  );

  return Object.keys(unavailableItemsByCartProductId).reduce(
    (reconciledItems, cartProductId) => {
      if (cartProductIds.has(cartProductId)) {
        reconciledItems[cartProductId] =
          unavailableItemsByCartProductId[cartProductId];
      }

      return reconciledItems;
    },
    {}
  );
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
  unavailableItemsByCartProductId: {},
  isCheckoutSubmitting: false,
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
  hasUnavailableItems(state) {
    return Object.keys(state.unavailableItemsByCartProductId).length > 0;
  },
  isCheckoutDisabled(state, getters) {
    return state.isCheckoutSubmitting || getters.hasUnavailableItems;
  },
  unavailableReason: (state) => (cartProductId) => {
    const normalizedCartProductId = normalizeCartProductId(cartProductId);

    if (!normalizedCartProductId) {
      return null;
    }

    return (
      state.unavailableItemsByCartProductId[normalizedCartProductId] || null
    );
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
    if (
      state.isCheckoutSubmitting ||
      Object.keys(state.unavailableItemsByCartProductId).length
    ) {
      return false;
    }

    const url = state.staticStore.url.apiOrder;
    const data = {
      cartId: state.cart.id,
    };
    commit("setCheckoutSubmitting", true);

    try {
      const result = await axios.post(url, data, apiConfig);

      if (result.data && StatusCodes.CREATED === result.status) {
        commit("clearUnavailableItems");
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

        return true;
      }

      return false;
    } catch (error) {
      const unavailableItemsByCartProductId =
        getUnavailableItemsByCartProductId(error, state.cart);

      if (unavailableItemsByCartProductId) {
        commit("setUnavailableItems", unavailableItemsByCartProductId);

        return false;
      }

      throw error;
    } finally {
      commit("setCheckoutSubmitting", false);
    }
  },
};

const mutations = {
  setCart(state, cart) {
    state.cart = cart;
    state.unavailableItemsByCartProductId = reconcileUnavailableItems(
      state.unavailableItemsByCartProductId,
      cart
    );
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
  setUnavailableItems(state, unavailableItemsByCartProductId) {
    state.unavailableItemsByCartProductId = {
      ...unavailableItemsByCartProductId,
    };
  },
  clearUnavailableItems(state) {
    state.unavailableItemsByCartProductId = {};
  },
  setCheckoutSubmitting(state, value) {
    state.isCheckoutSubmitting = value;
  },
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations,
};
