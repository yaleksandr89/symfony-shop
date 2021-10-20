<template>
  <div class="table-additional-selection">
    <OrderProductAdd/>
    <hr>
    <OrderProductItem
      v-for="(orderProduct, index) in staticStore.orderProducts"
      :key="orderProduct.id"
      :order-product="orderProduct"
      :index="index"
      />
    <hr>
  </div>
</template>

<script>
import {mapActions, mapState} from "vuex";
import OrderProductItem from "./components/OrderProductItem";
import OrderProductAdd from "./components/OrderProductAdd";

export default {
  components: {OrderProductAdd, OrderProductItem},
  created() {
    this.getCategories();
  },
  computed: {
    ...mapState("products", ["staticStore"]),
  },
  methods: {
    ...mapActions("products", ["getCategories"])
  },
}
</script>