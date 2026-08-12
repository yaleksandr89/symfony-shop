<template>
  <tr
    :class="{ 'cart-product-unavailable': isUnavailable }"
    :data-cart-product-id="cartProduct.id"
    :data-unavailable-reason="unavailableReason || null"
  >
    <td class="product-col">
      <div class="text-center">
        <figure>
          <a v-if="!isUnavailable" :href="urlShowProduct" target="_blank">
            <img
              :src="getUrlProductImage(productImage)"
              :alt="cartProduct.product.title"
            />
          </a>
          <img
            v-else
            :src="getUrlProductImage(productImage)"
            :alt="cartProduct.product.title"
          />
        </figure>
        <div class="product-title">
          <a v-if="!isUnavailable" :href="urlShowProduct" target="_blank">{{
            cartProduct.product.title
          }}</a>
          <span v-else>{{ cartProduct.product.title }}</span>
          <p
            v-if="isUnavailable"
            class="cart-product-unavailable-message"
            data-cart-product-unavailable-message
          >
            {{ unavailableMessage }}
          </p>
        </div>
      </div>
    </td>
    <td class="price-col">${{ cartProduct.product.price }}</td>
    <td class="quantity-col">
      <input
        v-model.number="quantity"
        type="number"
        class="form-control"
        min="1"
        :max="productQuantityMax"
        step="1"
        :disabled="isSaving || isUnavailable"
        @change="saveQuantity"
      />
    </td>
    <td class="total-col">${{ productPrice }}</td>
    <td class="remove-col">
      <a
        href="#"
        class="btn-remove"
        title="Remove product"
        data-remove-cart-product
        @click.prevent="removeCartProduct(cartProduct.id)"
      >
        X
      </a>
    </td>
  </tr>
</template>

<script>
import { mapActions, mapState } from "vuex";
import { formatCents, multiplyPriceToCents } from "../../../../utils/money";

export default {
  name: "CartProductItem",
  props: {
    cartProduct: {
      type: Object,
      default: () => {},
    },
    unavailableReason: {
      type: String,
      default: null,
    },
  },
  data() {
    return {
      quantity: 1,
      isSaving: false,
    };
  },
  computed: {
    ...mapState("cart", ["staticStore"]),
    productImage() {
      const productImages = this.cartProduct.product.productImages;
      return productImages.length ? productImages[0] : null;
    },
    productPrice() {
      return formatCents(
        multiplyPriceToCents(this.cartProduct.product.price, this.quantity)
      );
    },
    urlShowProduct() {
      return (
        this.staticStore.url.viewProduct + "/" + this.cartProduct.product.uuid
      );
    },
    productQuantityMax() {
      return Number(this.cartProduct.product.quantity);
    },
    isUnavailable() {
      return (
        this.unavailableReason === "deleted" ||
        this.unavailableReason === "unpublished"
      );
    },
    unavailableMessage() {
      if (this.unavailableReason === "deleted") {
        return this.staticStore.localization.product_deleted;
      }

      if (this.unavailableReason === "unpublished") {
        return this.staticStore.localization.product_unpublished;
      }

      return null;
    },
  },
  watch: {
    "cartProduct.quantity": {
      immediate: true,
      handler(quantity) {
        if (!this.isSaving) {
          this.quantity = quantity;
        }
      },
    },
  },
  methods: {
    ...mapActions("cart", ["removeCartProduct", "updateCartProductQuantity"]),
    getUrlProductImage(productImage) {
      return (
        this.staticStore.url.assetImageProducts +
        "/" +
        this.cartProduct.product.id +
        "/" +
        productImage.filenameSmall
      );
    },
    async saveQuantity() {
      if (this.isSaving || this.isUnavailable) {
        return;
      }

      const requestedQuantity = Number(this.quantity);
      const persistedQuantity = this.cartProduct.quantity;
      const stock = this.productQuantityMax;

      if (
        !Number.isInteger(requestedQuantity) ||
        requestedQuantity < 1 ||
        !Number.isInteger(stock) ||
        stock < 1
      ) {
        this.quantity = persistedQuantity;
        return;
      }

      const quantity = Math.min(requestedQuantity, stock);
      this.quantity = quantity;
      if (quantity === persistedQuantity) {
        return;
      }

      this.isSaving = true;
      try {
        const updated = await this.updateCartProductQuantity({
          cartProductId: this.cartProduct.id,
          quantity,
          stock,
        });
        this.quantity = updated ? this.cartProduct.quantity : persistedQuantity;
      } catch (error) {
        this.quantity = this.cartProduct.quantity;
      } finally {
        this.isSaving = false;
      }
    },
  },
};
</script>
