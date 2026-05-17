(function(){
var wd_ret_u = window.location.href;
var wd_ret_dt = detectDeviceType();
var wd_ret_uAgent = navigator.userAgent;
var wd_ret_r = document.referrer;
function getCookie(cookieName) {
  return document.cookie.split('; ').reduce((value, cookie) => {
    const [name, val] = cookie.split('=');
    return (name === encodeURIComponent(cookieName)) ? decodeURIComponent(val) : value;
  }, null);
}
const wd_ret_uid = getCookie('wd_ret_uid');
function setCookie(cookieName, cookieValue) {
  document.cookie = `${encodeURIComponent(cookieName)}=${encodeURIComponent(cookieValue)}; expires=${(new Date(Date.now() + 86400000)).toUTCString()}; path=/`;
}
if(!wd_ret_uid){
  setCookie('wd_ret_uid', generateUUID());
}
function detectDeviceType() {
  var wd_ret_userAgent = navigator.userAgent;
  
  if (/iPhone|iPad|iPod/i.test(wd_ret_userAgent)) {
    return "iOS";
  } else if (/Android/i.test(wd_ret_userAgent)) {
    return "Android";
  } else if (/Windows Phone/i.test(wd_ret_userAgent)) {
    return "Windows Phone";
  } else if (/Windows NT/i.test(wd_ret_userAgent)) {
    return "Windows";
  } else if (/Macintosh/i.test(wd_ret_userAgent)) {
    return "Mac";
  } else if (/Linux/i.test(wd_ret_userAgent)) {
    return "Linux";
  } else {
    return "Unknown";
  }
}

function generateUUID() {
    var wd_ret_d = new Date().getTime();
    var wd_ret_uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      var wd_ret_r = (wd_ret_d + Math.random() * 16) % 16 | 0;
      wd_ret_d = Math.floor(wd_ret_d / 16);
      return (c == 'x' ? wd_ret_r : (wd_ret_r & 0x3 | 0x8)).toString(16);
    });
    return wd_ret_uuid;
    
}
var wd_ret_c = [];
  wd_ret_c.push({
      event: "viewPage",
      uxid: generateUUID(),
      page: wd_ret_u,
      device_type:wd_ret_dt,
      uAgent: wd_ret_uAgent,
      referrer: wd_ret_r
  });
var wd_ret_d = {};
wd_ret_d.data = wd_ret_c;
const wd_ret_wd_url = '//wd-ret.io/rtg/v1/tr/tagv2.php';
fetch(wd_ret_wd_url, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify(wd_ret_d)
})
.then((res) => res.json())
.then(response => {
  var wd_ret_r_s = response.id;
  let wd_ret_f_r = response.fid;
  if(!wd_ret_f_r){
    wd_ret_f_r = '7f975a56c761db6506eca0b37ce6ec87';
  }
  if(wd_ret_r_s) i_s(wd_ret_r_s);
  if(wd_ret_f_r) segment(wd_ret_f_r);
})
.catch(error => {
});
function i_s(wd_ret_r_s){
  var wd_ret_rs_d = document.createElement('script');
  wd_ret_rs_d.src = '//wd-ret.io/rtg/v1/tr/hove/'+wd_ret_r_s+'.js';
  wd_ret_rs_d.async = true;
  document.head.appendChild(wd_ret_rs_d);
}
// function segment(wd_ret_r_s){
//   var wd_ret_s_d = document.createElement('script');
//   wd_ret_s_d.src = '//wd-ret.io/rtg/v1/js/segment/segment.js?brack='+wd_ret_r_s;
//   wd_ret_s_d.async = true;
//   document.head.appendChild(wd_ret_s_d);
// }

})();