<template>
  <div class="mb-2">
    Total: <strong>${{ totalPrice }}</strong>
  </div>
</template>

<script>
import {mapGetters} from "vuex";

export default {
  name: "CartTotalPrice",
  computed: {
    ...mapGetters('cart', ['totalPrice'])
  },
}
</script>
