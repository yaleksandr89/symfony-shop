<template>
  <div class="row">
    <div class="col-lg-12 order-block">
      <div class="order-content">
        <Alert />
        <div v-if="showCartContent">
          <CartProductList />
          <CartTotalPrice />
          <div v-if="isNotEmptyCart">
            <a
              v-if="isUserLoggedIn"
              href="#"
              class="btn btn-success mb-3 text-white"
              @click.prevent="makeOrder"
            >
              {{ staticStore.localization.make_order }}
            </a>
            <a
              v-else
              href="#"
              class="btn btn-success mb-3 text-white"
              @click="redirectToLoginPage"
            >
              {{ staticStore.localization.login }}
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import CartProductList from "./components/CartProductList";
import CartTotalPrice from "./components/CartTotalPrice";
import { mapActions, mapState } from "vuex";
import Alert from "./components/Alert";

export default {
  name: "App",
  components: { Alert, CartTotalPrice, CartProductList },
  computed: {
    ...mapState("cart", ["cart", "isSentForm", "staticStore"]),
    showCartContent() {
      return !this.isSentForm && Object.keys(this.cart).length;
    },
    isNotEmptyCart() {
      return this.cart.cartProducts.length;
    },
    isUserLoggedIn() {
      return this.staticStore.user.isLoggedIn;
    },
  },
  created() {
    this.getCart();
  },
  methods: {
    ...mapActions("cart", ["getCart", "makeOrder"]),
    redirectToLoginPage() {
      location.href = this.staticStore.url.loginPage;
    },
  },
};
</script>
