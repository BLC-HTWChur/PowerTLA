/*jslint white: true */
/*jslint vars: true */
/*jslint sloppy: true */
/*jslint browser: true */
/*jslint todo: true */
/*jslint unparam: true */
/*jslint bitwise: true*/

/*global global*/

(function (glob) {
    var RSD = {},
        apis = {};
    var jq,
        rsd;

    /**
     * moves one directory up on a given path.
     */
    function dirname(path) {
        if (path !== "/") {
            var aDir = path.split("/");
            if (aDir && aDir.length > 1) {
                aDir.pop();
            }

            var dir = aDir.join("/");
            return dir.length ? dir : "/";
        }
        return path;
    }

    /**
     * Remote Service Definition accessor.
     *
     * returns the RSD object or undefined (if not yet loaded)
     */
    function getRSD() {
        return rsd;
    }

    /**
     * tries to load the remote service definition from the active href.
     *
     * if no RSD meta-tag is provided in the document, this function will
     * try to detect the RSD by traversing the href path and look for
     * PowerTLA's rsd.php
     *
     * The function accepts a callback function that will be called when
     * the detection process terminates. If no RSD can be found, the
     * callback will receive and empty rsd object (without any properties).
     * Otherwise the identified rsd will be provided.
     */
    function loadRSD(cbFunc, bind) {
        if (!bind) {
            bind = RSD;
        }

        var host, dir;
        if (glob.document &&
            glob.document.location) {
            host  = glob.document.location.origin;
            dir   = glob.document.location.pathname;
        }

        function tryRSDPath(path) {
            if (jq && jq.ajax) {
                var purl = host + path;
                if (path.indexOf("https://") == 0 ||
                    path.indexOf("http://") == 0 ||
                    path.indexOf("//") == 0) {
                    purl = path;
                }

                jq.ajax({
                    type: "GET",
                    dataType: "json",
                    url: purl,
                    success: function (data) {
                        rsd = data;
                        apis = {};
                        Object.keys(rsd.apis).forEach(function (api) {
                            apis[api] = rsd.apis[api].apiLink;
                        });
                        cbFunc.call(bind, rsd);
                    },
                    error: function (xhr, msg) {
                        if (xhr.status === 404 && dirname !== "/") {
                            dir = dirname(dir);
                            tryRSDPath();
                        }
                        else {
                            // call with an empty document
                            cbFunc.call(bind, {});
                        }
                    }
                });
            }
        }

        function tryServicesPath() {
            if (jq && jq.ajax) {
                var serviceType = "application/x-rsd+json";
                jq.ajax({
                    type: "GET",
                    dataType: "text",
                    url: host + "/services.txt",
                    success: function (data) {
                        var lines = data.split("\n");
                        lines.forEach(function(line) {
                            line.trim();
                            if (line.length) {
                                var ass = line.split(";");
                                ass[0] = ass[0].trim();
                                ass[1] = ass[1].trim();
                                if (ass[0] == serviceType) {
                                    tryRSDPath(ass[1]);
                                }
                            }
                        });
                    },
                    error: function (xhr, msg) {
                        if (xhr.status === 404 && dirname !== "/") {
                            dir = dirname(dir);
                            tryRSDPath();
                        }
                        else {
                            // call with an empty document
                            cbFunc.call(bind, {});
                        }
                    }
                });
            }
        }

        dir = dirname(dir);
        tryServicesPath();
    }

    /**
     * builds a request URL for the given protocol.
     *
     * if the protocol is not provided by the service this function
     * will return undefined.
     *
     * The function accepts an optional array containing url
     * encoded path parameters.
     *
     * @param {STRING} protocol name
     * @param {ARRAY} path parameters
     * @param {MIXED} query parameters
     *
     * for query parameter is expeted that all data is not URL encoded.
     *
     * if query parameter is a string, it will be encoded with encodeURIComponent
     * and attached as a search query.
     *
     * if query parameter is an object, then all properties will be
     * added as key-value pairs and attached as GET parameters to the URL.
     */
    function getServiceURL(protocol, pathParam, queryParam) {
        var retval, tval, aParam = [];
        if (protocol &&
            protocol.length &&
            rsd &&
            rsd.hasOwnProperty("engineName") &&
            rsd.hasOwnProperty("homePageLink") &&
            apis.hasOwnProperty(protocol)) {

            //FIXME:: ENSURE CORRECT RSD2 behaviour
            retval = rsd.homePageLink + apis[protocol];

            if (Array.isArray(pathParam)) {
                retval += "/" + pathParam.join("/");
            }

            if (typeof queryParam === "object" &&
                     Object.getOwnPropertyNames(queryParam).length) {
                Object.getOwnPropertyNames(queryParam).forEach(function(pn){
                    if (queryParam[pn] !== undefined) {
                        if (typeof queryParam[pn] === "string") {
                            aParam.push(pn + "=" + encodeURIComponent(queryParam[pn]));
                        }
                        else if (typeof queryParam[pn] === "object") {
                            tval = JSON.stringify(queryParam[pn]);
                            aParam.push(pn + "=" + encodeURIComponent(tval));
                        }
                    }
                });
                if (aParam.length) {
                    retval += "?" + aParam.join("&");
                }
            }
            else if (typeof queryParam === "string") {
                retval += "?" + encodeURIComponent(queryParam);
            }
        }
        return retval;
    }

    /**
     * initialises the RSD process.
     *
     * This method emits the rsdready event when the initialisation has completed.
     */
    function init() {
        loadRSD(function() {
            console.log("ready!");
            jq(glob.document).trigger("rsdready");
        });
    }

    /**
     * register a callback for flexible readiness registration.
     */
    function readyInit(cbReady) {
        if (typeof cbReady === "function") {
            if (rsd && rsd.engine) {
                console.log("immediately init");
                cbReady.call(glob.document);
            }
            else {
                console.log("wait for rsd init");
                jq(glob.document).bind("rsdready",
                                       cbReady);
            }
        }
        else {
            throw new Error("ready requires a callback function as parameter 1");
        }
    }

    /** ******************************************************************
     * Define external accessors
     */

    RSD.ready      = readyInit;
    RSD.get        = getRSD;
    RSD.load       = loadRSD;
    RSD.serviceURL = getServiceURL;

    /** ******************************************************************
     * Expose the LRS API
     */

    if (glob.define && glob.define.amd) {
        // RequireJS  stuff
        glob.define(["jquery"], function (jQuery) {
            jq = jQuery;
            init();
            return RSD;
        });
    }
    else {
        // any other environment
        if (glob.jQuery) {
            jq = glob.jQuery;
            init();
        }
        glob.rsd = RSD;
    }

    // load the RSD information

} (
    (function factory() {
        if (window) {
            return window;
        }
        if (global) {
            return global;
        }

        return {};
    }())
));