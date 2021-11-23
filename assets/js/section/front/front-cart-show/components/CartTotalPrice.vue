<template>
  <div class="mb-2">
    {{ staticStore.localization.total_price }}: <strong>${{ totalPrice }}</strong>
  </div>
</template>

<script>
import {mapGetters, mapState} from "vuex";

export default {
  name: "CartTotalPrice",
  computed: {
    ...mapState('cart', ['staticStore']),
    ...mapGetters('cart', ['totalPrice'])
  },
}
</script>
