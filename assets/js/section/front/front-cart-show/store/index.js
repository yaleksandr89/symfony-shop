import Vue from "vue";
import Vuex from "vuex";
import cart from "./modules/cart";

Vue.use(Vuex);

const debug = process.env.NODE_ENV !== "production";

export default new Vuex.Store({
  modules: {
    cart,
  },
  strict: debug,
});
