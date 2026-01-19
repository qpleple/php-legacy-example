/*!
 * jQuery JavaScript Library v1.2.6
 * Minified version placeholder - in production would use actual jQuery 1.2.x
 * For this legacy simulation, we'll use a minimal implementation
 */
(function(window) {
    var $ = function(selector) {
        return new $.fn.init(selector);
    };

    $.fn = $.prototype = {
        init: function(selector) {
            if (!selector) return this;

            if (typeof selector === 'string') {
                if (selector.charAt(0) === '<') {
                    // Create element
                    var div = document.createElement('div');
                    div.innerHTML = selector;
                    this[0] = div.firstChild;
                    this.length = 1;
                } else {
                    // Query selector
                    var elements = document.querySelectorAll(selector);
                    for (var i = 0; i < elements.length; i++) {
                        this[i] = elements[i];
                    }
                    this.length = elements.length;
                }
            } else if (selector.nodeType) {
                this[0] = selector;
                this.length = 1;
            } else if (typeof selector === 'function') {
                // DOM ready
                if (document.readyState === 'complete') {
                    selector();
                } else {
                    document.addEventListener('DOMContentLoaded', selector);
                }
            }
            return this;
        },

        each: function(callback) {
            for (var i = 0; i < this.length; i++) {
                callback.call(this[i], i, this[i]);
            }
            return this;
        },

        on: function(event, selector, handler) {
            if (typeof selector === 'function') {
                handler = selector;
                selector = null;
            }

            return this.each(function() {
                var elem = this;
                if (selector) {
                    elem.addEventListener(event, function(e) {
                        var target = e.target;
                        while (target && target !== elem) {
                            if (target.matches(selector)) {
                                handler.call(target, e);
                                break;
                            }
                            target = target.parentNode;
                        }
                    });
                } else {
                    elem.addEventListener(event, handler);
                }
            });
        },

        click: function(handler) {
            return this.on('click', handler);
        },

        change: function(handler) {
            return this.on('change', handler);
        },

        submit: function(handler) {
            return this.on('submit', handler);
        },

        val: function(value) {
            if (value === undefined) {
                return this[0] ? this[0].value : '';
            }
            return this.each(function() {
                this.value = value;
            });
        },

        text: function(value) {
            if (value === undefined) {
                return this[0] ? this[0].textContent : '';
            }
            return this.each(function() {
                this.textContent = value;
            });
        },

        html: function(value) {
            if (value === undefined) {
                return this[0] ? this[0].innerHTML : '';
            }
            return this.each(function() {
                this.innerHTML = value;
            });
        },

        attr: function(name, value) {
            if (value === undefined) {
                return this[0] ? this[0].getAttribute(name) : '';
            }
            return this.each(function() {
                this.setAttribute(name, value);
            });
        },

        removeAttr: function(name) {
            return this.each(function() {
                this.removeAttribute(name);
            });
        },

        addClass: function(className) {
            return this.each(function() {
                this.classList.add(className);
            });
        },

        removeClass: function(className) {
            return this.each(function() {
                this.classList.remove(className);
            });
        },

        toggleClass: function(className) {
            return this.each(function() {
                this.classList.toggle(className);
            });
        },

        hasClass: function(className) {
            return this[0] ? this[0].classList.contains(className) : false;
        },

        show: function() {
            return this.each(function() {
                this.style.display = '';
            });
        },

        hide: function() {
            return this.each(function() {
                this.style.display = 'none';
            });
        },

        toggle: function() {
            return this.each(function() {
                this.style.display = this.style.display === 'none' ? '' : 'none';
            });
        },

        append: function(child) {
            return this.each(function() {
                if (typeof child === 'string') {
                    this.insertAdjacentHTML('beforeend', child);
                } else if (child[0]) {
                    this.appendChild(child[0]);
                } else {
                    this.appendChild(child);
                }
            });
        },

        prepend: function(child) {
            return this.each(function() {
                if (typeof child === 'string') {
                    this.insertAdjacentHTML('afterbegin', child);
                } else if (child[0]) {
                    this.insertBefore(child[0], this.firstChild);
                } else {
                    this.insertBefore(child, this.firstChild);
                }
            });
        },

        remove: function() {
            return this.each(function() {
                if (this.parentNode) {
                    this.parentNode.removeChild(this);
                }
            });
        },

        closest: function(selector) {
            var elem = this[0];
            while (elem) {
                if (elem.matches && elem.matches(selector)) {
                    return $(elem);
                }
                elem = elem.parentNode;
            }
            return $();
        },

        parent: function() {
            return this[0] ? $(this[0].parentNode) : $();
        },

        find: function(selector) {
            if (!this[0]) return $();
            var result = $();
            var elements = this[0].querySelectorAll(selector);
            for (var i = 0; i < elements.length; i++) {
                result[i] = elements[i];
            }
            result.length = elements.length;
            return result;
        },

        first: function() {
            return this[0] ? $(this[0]) : $();
        },

        last: function() {
            return this.length ? $(this[this.length - 1]) : $();
        },

        eq: function(index) {
            return this[index] ? $(this[index]) : $();
        },

        prop: function(name, value) {
            if (value === undefined) {
                return this[0] ? this[0][name] : undefined;
            }
            return this.each(function() {
                this[name] = value;
            });
        },

        data: function(name, value) {
            if (value === undefined) {
                return this[0] ? this[0].dataset[name] : undefined;
            }
            return this.each(function() {
                this.dataset[name] = value;
            });
        },

        css: function(name, value) {
            if (typeof name === 'object') {
                return this.each(function() {
                    for (var key in name) {
                        this.style[key] = name[key];
                    }
                });
            }
            if (value === undefined) {
                return this[0] ? getComputedStyle(this[0])[name] : '';
            }
            return this.each(function() {
                this.style[name] = value;
            });
        },

        serialize: function() {
            if (!this[0] || this[0].tagName !== 'FORM') return '';
            var formData = new FormData(this[0]);
            var params = [];
            formData.forEach(function(value, key) {
                params.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
            });
            return params.join('&');
        },

        clone: function() {
            return this[0] ? $(this[0].cloneNode(true)) : $();
        }
    };

    $.fn.init.prototype = $.fn;

    // Static methods
    $.ajax = function(options) {
        var xhr = new XMLHttpRequest();
        xhr.open(options.type || 'GET', options.url, true);

        if (options.contentType !== false) {
            xhr.setRequestHeader('Content-Type', options.contentType || 'application/x-www-form-urlencoded');
        }

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status >= 200 && xhr.status < 300) {
                    var response = xhr.responseText;
                    if (options.dataType === 'json') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {}
                    }
                    if (options.success) options.success(response);
                } else {
                    if (options.error) options.error(xhr);
                }
                if (options.complete) options.complete(xhr);
            }
        };

        xhr.send(options.data || null);
        return xhr;
    };

    $.get = function(url, data, callback) {
        if (typeof data === 'function') {
            callback = data;
            data = null;
        }
        if (data) {
            var params = [];
            for (var key in data) {
                params.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
            }
            url += (url.indexOf('?') === -1 ? '?' : '&') + params.join('&');
        }
        return $.ajax({ url: url, type: 'GET', success: callback });
    };

    $.post = function(url, data, callback) {
        if (typeof data === 'function') {
            callback = data;
            data = null;
        }
        var postData = null;
        if (data) {
            var params = [];
            for (var key in data) {
                params.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
            }
            postData = params.join('&');
        }
        return $.ajax({ url: url, type: 'POST', data: postData, success: callback });
    };

    $.extend = function(target) {
        for (var i = 1; i < arguments.length; i++) {
            var source = arguments[i];
            for (var key in source) {
                target[key] = source[key];
            }
        }
        return target;
    };

    $.isArray = Array.isArray || function(obj) {
        return Object.prototype.toString.call(obj) === '[object Array]';
    };

    $.trim = function(str) {
        return str ? str.replace(/^\s+|\s+$/g, '') : '';
    };

    window.$ = window.jQuery = $;
})(window);
