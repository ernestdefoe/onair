import app from 'flarum/admin/app';

// Settings + permissions are declared via the Admin extender in ./extend.js
// (re-exported as `extend` from js/admin.js). This initializer is reserved for
// any future imperative admin wiring.
app.initializers.add('ernestdefoe-onair', () => {});
