/**
 * Add element to array if not already exists
 * */
Array.prototype.pushIfNotExist = function(item) {
    if (this.indexOf(item) === -1) this.push(item);
};
