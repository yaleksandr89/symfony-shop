<template>
  <tr>
    <td class="product-col">
      <div class="text-center">
        <figure>
          <a :href="urlShowProduct" target="_blank">
            <img
              :src="getUrlProductImage(productImage)"
              :alt="cartProduct.product.title"
            />
          </a>
        </figure>
        <div class="product-title">
          <a :href="urlShowProduct" target="_blank">{{
            cartProduct.product.title
          }}</a>
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
        :disabled="isSaving"
        @change="saveQuantity"
      />
    </td>
    <td class="total-col">${{ productPrice }}</td>
    <td class="remove-col">
      <a
        href="#"
        class="btn-remove"
        title="Remove product"
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
      if (this.isSaving) {
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
