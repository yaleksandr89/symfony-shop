const DECIMAL_AMOUNT_PATTERN = /^(0|[1-9]\d*)(?:\.(\d{1,2}))?$/;

function parseDigitsToSafeInteger(digits, fieldName) {
  let value = 0;

  for (const digit of digits) {
    value = value * 10 + (digit.charCodeAt(0) - 48);

    if (!Number.isSafeInteger(value)) {
      throw new Error(`${fieldName} exceeds the safe integer range.`);
    }
  }

  return value;
}

function assertNonNegativeSafeInteger(value, fieldName) {
  if (!Number.isSafeInteger(value) || value < 0) {
    throw new Error(`${fieldName} must be a non-negative safe integer.`);
  }
}

export function decimalToCents(amount) {
  if (typeof amount !== "string" || !DECIMAL_AMOUNT_PATTERN.test(amount)) {
    throw new Error(
      "Amount must be a non-negative decimal string with no more than two fractional digits."
    );
  }

  const [, whole, fraction = ""] = amount.match(DECIMAL_AMOUNT_PATTERN);

  return parseDigitsToSafeInteger(whole + fraction.padEnd(2, "0"), "Amount");
}

export function multiplyPriceToCents(amount, quantity) {
  const priceCents = decimalToCents(amount);
  assertNonNegativeSafeInteger(quantity, "Quantity");

  const totalCents = priceCents * quantity;
  assertNonNegativeSafeInteger(totalCents, "Line total");

  return totalCents;
}

export function sumCartProductsToCents(cartProducts) {
  if (!Array.isArray(cartProducts)) {
    throw new Error("Cart products must be an array.");
  }

  return cartProducts.reduce((totalCents, cartProduct) => {
    if (!cartProduct || !cartProduct.product) {
      throw new Error("Cart product must include a product.");
    }

    const lineTotalCents = multiplyPriceToCents(
      cartProduct.product.price,
      cartProduct.quantity
    );
    const nextTotalCents = totalCents + lineTotalCents;
    assertNonNegativeSafeInteger(nextTotalCents, "Cart total");

    return nextTotalCents;
  }, 0);
}

export function formatCents(cents) {
  assertNonNegativeSafeInteger(cents, "Cents");

  const whole = Math.floor(cents / 100);
  const fraction = (cents % 100).toString().padStart(2, "0");

  return `${whole}.${fraction}`;
}
