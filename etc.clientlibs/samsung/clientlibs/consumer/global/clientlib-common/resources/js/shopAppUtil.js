let isWebView = false
class ShopAppUtil {
  constructor(params) {
		let startT = new Date().valueOf();
		console.log("★ startTime:", startT);
    this.params = params;
    window.addEventListener("flutterInAppWebViewPlatformReady", (event) => {
      isWebView = true;
      let responseT = new Date().valueOf();
      console.log("★ responseTime:", responseT);
      console.log("★ responseTime-startTime:", responseT-startT);
      console.log("flutter InAppWebViewPlatformReady, web view:", isWebView);
      params.readyCallback()
    });
  }
  callHandler = (methodName, ...params) => {
    if (isWebView) {
      return window.flutter_inappwebview.callHandler(methodName, ...params)
    } else {
      return Promise.reject(`Calling methodName: ${methodName}, but webview not identified`)
    }
  }
  logger = (info, value) => {
    this.params.logger && console.log(`${info} ${value}`)
  }
  isWebView = () => {
    this.logger('Returning isWebView: ', isWebView)
    return isWebView
  }
  getAppVersionCode = () => new Promise((resolve, reject) => {
    this.callHandler('getAppVersionCode')
      .then(result => {
        this.logger("App version", result)
        resolve(result)
      })
      .catch(err => {
        this.logger("Error in App version", err)
        reject(err)
      })
  })
  triggerAnalytics = (data) => new Promise((resolve, reject) => {
    this.callHandler('OnAnalyticsEvent', data)
      .then(result => {
        this.logger("OnAnalyticsEvent Success", result)
        resolve(JSON.stringify(result))
      })
      .catch(err => {
        this.logger("Error in OnAnalyticsEvent", err)
        reject(err)
      })
  })
  openExternalBrowser = (url) => new Promise((resolve, reject) => {
    this.callHandler('openExternalBrowser', url)
      .then(result => {
        this.logger("openExternalBrowser Success", result)
        resolve(JSON.stringify(result))
      })
      .catch(err => {
        this.logger("Error in openExternalBrowser", err)
        reject(err)
      })
  })
  setupCloseForBack = (exit, confirm, hide, backCallback) => {
    /*
      configureBackV2 (boolean exit, boolean confirm, boolean hide, final String overrideBackCallback)
      exit - to close the app
      confirm - Confirm before closing the app
      hide - hide the app (in recent apps tray)
      overrideBackCallback - Web view's handler for back click
    */
    this.callHandler('configureBackV2', exit, confirm, hide, backCallback)
      .then(function (result) {
        console.log(JSON.stringify(result));
      })
      .catch(function (err) {
        console.log("Error in configureBackV2", err)
      })
  }
  setupNormalBack = () => {
    this.callHandler('configureBackV2', false, false, false, '')
      .then(function (result) {
        console.log(JSON.stringify(result));
      })
      .catch(function (err) {
        console.log("Error in configureBackV2", err)
      })
  }
  getUserDetails = () => new Promise((resolve, reject) => {
    this.callHandler('getUserDetails', 'window.setUserDetails')
      .then(result => {
        this.logger("User Details", result)
        resolve(result)
      })
      .catch(err => {
        this.logger("Error in getUserDetails", err)
        reject(err)
      })
  })
  updateCartCount = (cartCount) => new Promise((resolve, reject) => {
    this.callHandler('updateCartCount', cartCount)
      .then(result => {
        this.logger("updated Cart Count", result)
        resolve(result)
      })
      .catch(err => {
        this.logger("Error in updateCartCount", err)
        reject(err)
      })
  })
  getToken = () => new Promise((resolve, reject) => {
    this.callHandler('getToken', false)
      .then(result => {
        this.logger("GetToken Success", result)
        resolve(result)
      })
      .catch(err => {
        this.logger("Error in getToken", err)
        reject(err)
      })
  })
  displayInAppReview = () => new Promise((resolve, reject) => {
    this.callHandler('displayInAppReview')
      .then(result => {
        this.logger("displayInAppReview success")
        resolve(result)
      })
      .catch(err => {
        this.logger("displayInAppReview failed")
        reject(err)
      })
  })
}
  // [START log_event]
  function logEvent(name, params) {
    if (!name) {
      return;
    }
  
    if (window.AnalyticsWebInterface) {
      // Call Android interface
      window.AnalyticsWebInterface.logEvent(name, JSON.stringify(params));
    } else if (window.webkit
        && window.webkit.messageHandlers
        && window.webkit.messageHandlers.firebase) {
      // Call iOS interface
      var message = {
        command: 'logEvent',
        name: name,
        parameters: params
      };
      window.webkit.messageHandlers.firebase.postMessage(message);
    } else {
      // No Android or iOS interface found
      console.log("No native APIs found.");
    }
  }
  // [END log_event]
  
  // [START set_user_property]
  function setUserProperty(name, value) {
    if (!name || !value) {
      return;
    }
  
    if (window.AnalyticsWebInterface) {
      // Call Android interface
      window.AnalyticsWebInterface.setUserProperty(name, value);
    } else if (window.webkit
        && window.webkit.messageHandlers
        && window.webkit.messageHandlers.firebase) {
      // Call iOS interface
      var message = {
        command: 'setUserProperty',
        name: name,
        value: value
     };
      window.webkit.messageHandlers.firebase.postMessage(message);
    } else {
      // No Android or iOS interface found
      console.log("No native APIs found.");
    }
  }
  // [END set_user_property]
