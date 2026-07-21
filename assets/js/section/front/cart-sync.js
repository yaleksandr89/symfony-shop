const syncKey = "__shopCartSync";

function createCartSync() {
  let cart;
  let inFlightRequest = null;
  let queuedRefresh = null;
  const subscribers = [];

  const publish = (nextCart) => {
    cart = nextCart;
    subscribers.slice().forEach((subscriber) => subscriber(cart));
  };

  const runRequest = (loader) => {
    const request = Promise.resolve()
      .then(loader)
      .then((nextCart) => {
        if (typeof nextCart === "undefined") {
          throw new Error("Cart loader must resolve to a cart state");
        }

        publish(nextCart);

        return nextCart;
      });

    inFlightRequest = request;
    request.then(
      () => {
        if (inFlightRequest === request) {
          inFlightRequest = null;
        }
      },
      () => {
        if (inFlightRequest === request) {
          inFlightRequest = null;
        }
      }
    );

    return request;
  };

  const load = (loader, { force = false } = {}) => {
    if (!force && typeof cart !== "undefined") {
      return Promise.resolve(cart);
    }

    if (!inFlightRequest) {
      return runRequest(loader);
    }

    if (!force) {
      return inFlightRequest;
    }

    if (!queuedRefresh) {
      queuedRefresh = inFlightRequest
        .catch(() => undefined)
        .then(() => runRequest(loader));
      queuedRefresh.then(
        () => {
          queuedRefresh = null;
        },
        () => {
          queuedRefresh = null;
        }
      );
    }

    return queuedRefresh;
  };

  const subscribe = (subscriber) => {
    subscribers.push(subscriber);

    if (typeof cart !== "undefined") {
      subscriber(cart);
    }

    return () => {
      const subscriberIndex = subscribers.indexOf(subscriber);

      if (subscriberIndex !== -1) {
        subscribers.splice(subscriberIndex, 1);
      }
    };
  };

  return {
    load,
    publish,
    subscribe,
  };
}

const cartSync = window[syncKey] || createCartSync();

window[syncKey] = cartSync;

export default cartSync;
