(() => {
  const host =
    window.location && window.location.hostname ? window.location.hostname : "";
  const isLocal = host === "localhost" || host === "127.0.0.1" || host === "";
  if (isLocal) {
    let sat = window._satellite;
    if (!sat || typeof sat !== "object") sat = {};

    if (typeof sat.getVar !== "function") sat.getVar = () => undefined;
    if (typeof sat.setVar !== "function") sat.setVar = () => {};
    if (typeof sat.track !== "function") sat.track = () => {};
    if (!sat.cookie) sat.cookie = {};
    if (typeof sat.cookie.get !== "function") sat.cookie.get = () => undefined;
    if (!sat.environment) sat.environment = {};
    if (typeof sat.environment.stage !== "string") sat.environment.stage = "local";

    const noop = () => {};
    for (let i = 0; i <= 60; i++) {
      const key = `_runScript${i}`;
      try {
        Object.defineProperty(sat, key, {
          value: noop,
          writable: false,
          configurable: false,
          enumerable: false,
        });
      } catch {
        sat[key] = noop;
      }
    }

    window._satellite = sat;
    window.eddlDataLayer = window.eddlDataLayer || [];
    window.fbq = window.fbq || (() => {});
    window._fbq = window._fbq || window.fbq;

    if (!window.__localProxyInstalled) {
      window.__localProxyInstalled = true;

      const shouldProxy = (url) => {
        if (!url || typeof url !== "string") return false;
        return (
          url.indexOf("https://www.samsung.com/aemapi/") === 0 ||
          (url.indexOf("https://www.samsung.com/etc.clientlibs/") === 0 &&
            url.indexOf("svg-sprite") !== -1 &&
            url.slice(-4) === ".svg") ||
          url.indexOf("https://api-recommender.bigdata.samsung.com/prodrec/recommendation") === 0
        );
      };

      const toProxy = (url) => `?proxy=1&url=${encodeURIComponent(url)}`;

      if (window.XMLHttpRequest && window.XMLHttpRequest.prototype) {
        const originalOpen = window.XMLHttpRequest.prototype.open;
        window.XMLHttpRequest.prototype.open = function () {
          try {
            if (arguments.length >= 2 && shouldProxy(arguments[1])) {
              arguments[1] = toProxy(arguments[1]);
            }
          } catch {}
          return originalOpen.apply(this, arguments);
        };
      }

      if (typeof window.fetch === "function" && typeof window.Request === "function") {
        const originalFetch = window.fetch.bind(window);
        window.fetch = (input, init) => {
          try {
            const url =
              typeof input === "string" ? input : input && input.url ? input.url : "";
            if (shouldProxy(url)) {
              const proxied = toProxy(url);
              input = typeof input === "string" ? proxied : new Request(proxied, input);
            }
          } catch {}
          return originalFetch(input, init);
        };
      }
    }
  }
})();

(() => {
  let winhref = window.location.href
    .replace("/content/samsung", "")
    .replace(".html", "/");
  if (winhref.indexOf("?") > 0) {
    winhref = winhref.substring(0, winhref.indexOf("?"));
  }

  let siteCode = winhref.split("/")[3];
  if (winhref.indexOf("samsung.com.cn") > 0) {
    siteCode = "cn";
  }

  let depth = winhref.split("/").length;
  const depthLast = winhref.split("/")[depth - 1];
  if (depthLast === "" || depthLast.charAt(0) === "?") {
    depth -= 1;
  }

  const pageName = "";
  const depth2 = "";
  const depth3 = "";
  const depth4 = "";
  const depth5 = "";

  window.digitalData = {
    page: {
      pageInfo: {
        siteCode: siteCode || "pk",
        pageName,
        pageID: "L2NvbnRlbnQvc2Ftc3VuZy9waw==",
        pageTrack: "home",
        originPlaform: "web",
      },
      pathIndicator: {
        depth_2: depth2,
        depth_3: depth3,
        depth_4: depth4,
        depth_5: depth5,
      },
    },
    user: {
      userDeviceList: [],
    },
    product: {
      category: "",
      model_code: "",
      model_name: "",
      model_price: "",
      list_price: "",
      displayName: "",
      pvi_type_code: "",
      pvi_type_name: "",
      pvi_subtype_code: "",
      pvi_subtype_name: "",
      pd_type: "",
      content_id: "",
      products: "",
      prodView: "",
    },
  };
})();

(() => {
  window.__fileData__ = window.__fileData__ || {};
  Object.assign(window.__fileData__, {
    "svg-sprite.svg": "20260423143947",
    "svg-sprite-retention.svg": "20260421132723",
  });
})();
