import Vue from "vue";
import Vuex from "vuex";
import products from "./modules/products";

Vue.use(Vuex);

const debug = process.env.NODE_ENV !== "production";

export default new Vuex.Store({
  modules: {
    products,
  },
  strict: debug,
});
