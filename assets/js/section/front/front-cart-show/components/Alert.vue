<template>
  <div v-if="alert" :class="alertClass">
    {{ alert.message }}
  </div>
</template>

<script>
import { mapState } from "vuex";

export default {
  name: "Alert",
  computed: {
    ...mapState("cart", ["alert"]),
    alertClass() {
      return "alert alert-" + this.alert.type;
    },
  },
};
</script>
