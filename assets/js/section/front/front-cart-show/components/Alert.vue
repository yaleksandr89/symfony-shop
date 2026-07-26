<template>
  <div
    v-if="displayAlert"
    :class="alertClass"
    :data-cart-unavailable-alert="hasUnavailableItems ? '' : null"
  >
    {{ displayAlert.message }}
  </div>
</template>

<script>
import { mapGetters, mapState } from "vuex";

export default {
  name: "CartAlert",
  computed: {
    ...mapState("cart", ["alert", "staticStore"]),
    ...mapGetters("cart", ["hasUnavailableItems"]),
    displayAlert() {
      if (this.hasUnavailableItems) {
        return {
          type: "warning",
          message: this.staticStore.localization.unavailable_products,
        };
      }

      return this.alert && this.alert.message ? this.alert : null;
    },
    alertClass() {
      return "alert alert-" + this.displayAlert.type;
    },
  },
};
</script>
