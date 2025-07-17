module.exports = function checkProperty(obj, prop) {
  return obj && Object.prototype.hasOwnProperty.call(obj, prop) && obj[prop] !== undefined && obj[prop] !== null;
};