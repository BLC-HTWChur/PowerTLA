/*jslint white: true */
/*jslint vars: true */
/*jslint sloppy: true */
/*jslint browser: true */
/*jslint todo: true */
/*jslint unparam: true */
/*jslint bitwise: true*/

/*global global, rsd*/

/**
 * Frontend Component for accessing the PowerTLA LRS. This service abstracts
 * XAPI statement generation and process management
 */
(function (glob) {
    /** ******************************************************************
     * Private Members
     */

    var jq,
        bSync         = false,
        RSD,
        autoFinish    = true,

        /**
         * Local activity stream.
         *
         * Must not be exposed externally.
         */
        stream      = [], ///< contains those actions that need to go back to the lrs
        oldStream   = [], ///< parts of the stream that come form the lrs
        upstream    = [], ///< upload cache

        /**
         * internal action map of not terminated actions.
         *
         * This adheres that actions if the official stream must not get
         * updated.
         */
        ongoing = {},

        /**
         * maps uuids to the local activity stream.
         *
         * This is used for quick access to actions.
         */
        uuidMap = {},

        /**
         * active Contexts
         */
        context = {},

        /**
         * The ACTIVE actor context.
         */
        actor = {},

        /**
         * State Documents
         */
        stateDocs = {},

        /**
         * The local Actor context.
         *
         * The local actor is the active user interacting with the system.
         *
         * This is not necessarily the same actor than the actions that are
         * recorded.
         */
        localActor = {},

        /**
         * The main accessor object.
         */
        LRS = {};

    /**
     * Helper function for creating random UUIDs
     */
    function makeUUID() {
        var uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx',
            retval;

        function mapChar(c) {
            var r = Math.random()*16|0,
                v = c === 'x' ? r : (r&0x3|0x8);
            return v.toString(16);
        }

        while (retval === undefined ||
               uuidMap.hasOwnProperty(retval) ||
               ongoing.hasOwnProperty(retval)) {
            retval = uuid.replace(/[xy]/g, mapChar);
        }
        return retval;
    }

    /**
     * Deep clone of an object
     *
     * Helper function to avoid accidental cross references
     */
    function cloneObject(obj) {
        var object;
        if (typeof obj === 'string') {
            return obj.substr(0); // clone the string
        }

        if (typeof obj === "object") {
            if (Array.isArray(obj)) {
                object = [];
                obj.forEach(function (e) {
                    object.push(cloneObject(e));
                });
            }
            else {
                object = {};
                Object.getOwnPropertyNames(obj).forEach(function (k) {
                    object[k] = cloneObject(obj[k]);
                });
            }
        }
        return object;
    }

    /**
     * returns a unmutable copy of the stream
     *
     * This function combines any previous data (provided by the server) with any
     * newly recorded statements.
     */
    function getStream() {
        return cloneObject(stream.concat(oldStream));
    }

    /**
     * Start an activity context.
     *
     * Provides a safe way for setting and unsetting action contexts.
     *
     * This function accepts a partial action context object. (see Context in the Spec)
     *
     * It will *extend* any existing activty context that is already active.
     */
    function startContext(nContext) {
        var bad = false,
            aOKNames = ["registration", "contextActivities",
                        "statement", "language"],
            bOKNames = ["parent", "grouping", "category", "other"],
            mapObj = {};

        if (typeof nContext === 'object') {
            Object.getOwnPropertyNames(nContext).forEach(function (nm) {
                if (aOKNames.indexOf(nm) >= 0) {
                    if (nm === "contextActivities" &&
                        typeof nContext[nm] === "object") {
                        mapObj[nm] = {};
                        Object.getOwnPropertyNames(nContext[nm]).forEach(function (at) {
                            if (bOKNames.indexOf(at) >= 0) {
                                mapObj[nm][at] = nContext[nm][at];
                            }
                            else {
                                bad = true;
                            }
                        });
                    }
                    else if (typeof nContext[nm] === "string") {
                        if (nm === "language") {
                            if (nContext[nm].length === 2 ||
                                (nContext[nm].length === 5 &&
                                 nContext[nm].indexOf("-") === 2)) {
                                mapObj[nm] = nContext[nm];
                            }
                        }
                        else {
                            mapObj[nm] = nContext[nm];
                        }
                    }
                }
                else {
                    bad = true;
                }
            });

            if (!bad) {
                Object.getOwnPropertyNames(mapObj).forEach(function (nm) {
                    context[nm] = mapObj[nm];
                });
            }
        }
    }

    /**
     * Removes a partial context from the activty context
     *
     * This function expects a partial context obejct.
     */
    function endContext(nContext) {
        if (typeof nContext === 'object') {
            Object.getOwnPropertyNames(nContext).forEach(function (nm) {
                if (context.hasOwnProperty(nm)) {
                    delete context[nm];
                }
            });
        }
    }

    /**
     * Exposes the context object.
     */
    function getContext() {
        return cloneObject(context);
    }

    /**
     * resets the context
     */
    function resetContext() {
        context = {};
    }

    /**
     * sets an actor by PowerTLA token.
     *
     * This function ONLY sets valid actor statements.
     */
    function setActor(nActor) {
        var tActor = {"objectType": "Agent"};
        var bOK = false;
        if (typeof nActor === "object") {
            if (nActor.openid &&
                typeof nActor.openid === "string") {
                tActor.openid = nActor.openid;
                bOK = true;
            }

            // handle the other flags
            if (nActor.name &&
                typeof nActor.name === "string") {
                tActor.name = nActor.name;
            }

            if (nActor.mbox &&
                typeof nActor.mbox === "string" &&
                nActor.mbox.indexOf("mailto:") === 0) {
                tActor.mbox = nActor.mbox;
                bOK = true;
            }

            if (nActor.mbox_sha1sum &&
                typeof nActor.mbox_sha1sum === "string") {
                tActor.mobx_sha1shum = nActor.mbox_sha1sum;
                bOK = true;
            }

            if (nActor.account &&
                typeof nActor.account === "object") {
                tActor.account = {};
                if (nActor.account.name &&
                    typeof nActor.account.name === "string") {
                    tActor.account.name = nActor.account.name;
                    bOK = true;
                }
                if (nActor.account.homePage &&
                    typeof actor.account.homePage === 'string') {
                    tActor.account.homePage = nActor.account.homePage;
                    bOK = true;
                }
            }
        }
        if (bOK) {
            actor = tActor;
        }
    }

    /**
     * removes the active actor, unless it is the same as the
     * local actor.
     */
    function unsetActor() {
        actor = localActor;
    }

    /**
     * starts an action and returns its uuid.
     *
     * this function instantiates a new XAPI action, without entering it
     * to the activity stream. With any other respect, this function creates
     * a valid XAPI statement, that can get used for contextualising other
     * actions. However, a remote LRS may reject the other statements, if
     * the contextualising action is not in the stream.
     *
     * The action is only send to the remote LRS if it is terminated with
     * endAction().
     *
     * The combination of startAction() and endAction is useful for the
     * automatic timing of durations.
     */
    function startAction(verbid, objectid) {
        var uuid;

        if (verbid &&
            verbid.length) {
            if (!(objectid &&
                  objectid.length) &&
                  glob.document) {
                objectid = document.location.href;
            }

            uuid = makeUUID();


            var now = new Date();
            var action = {
                "id": uuid,
                "timestamp": now.toISOString(),
                "actor": cloneObject(actor),
                "verb": {
                    id: verbid
                },
                "object": {
                    id: objectid
                }
            };

            if (Object.getOwnPropertyNames(context).length) {
                action.context = cloneObject(context);
            }

            ongoing[uuid] = {"action": action, "start": now};
        }

        return uuid;
    }

    /**
     * ends an action and marks it for delivery to the remote LRS.
     *
     * This method accepts an optional result object (as defined by the spec).
     *
     * This method requires a UUID pointing to an unfinished action that was
     * initiated with startAction().
     *
     * Actions that were previously finished cannot be extended using this
     * method.
     */
    function finishAction(uuid, result) {
        if (uuid &&
            ongoing.hasOwnProperty(uuid)) {
            var now = new Date();
            var duration = "";

            if (result &&
                typeof result === "object") {
                var bad = false,
                    aOKResults = ["score", "completion", "success",
                                  "response", "duration", "extensions"];
                // NOTE - Duration is accepted as a result, but it will be
                // overwritten with the real duration from start to finish.

                // check for valid fields
                var arv = Object.getOwnPropertyNames(result);
                arv.some(function (n) {
                    if (aOKResults.indexOf(n) < 0) {
                        bad = true;
                        return bad;
                    }
                });
                if (!bad) {
                    ongoing[uuid].action.result = cloneObject(result);
                }
            }
            else {
                ongoing[uuid].action.result = {};
            }

            // add the real duration
            var dt, s, m, h;

            dt = now - ongoing[uuid].start;

            s = Math.floor(dt/1000);
            m = Math.floor(s/60);
            h = Math.floor(m/60);

            s = s - m*60;
            m = m - h*60;

            duration = "PT" + h + "H" + m + "M" + s + "S";

            ongoing[uuid].action.result.duration = duration;

            uuidMap[uuid] = stream.length;
            stream.push(ongoing[uuid].action);

            delete ongoing[uuid];
        }
    }

    /**
     * writes an action directly to the activity stream
     *
     * This function is pretty much like a combination of
     * startAction() and finishAction(), while it won't touch any duration
     * property if provided.
     *
     * returns the new UUID
     *
     * verb: verb ID
     * object: object ID
     */
    function recordAction(verbid, objectid, result) {
        var uuid, arv;

        if (verbid &&
            verbid.length) {

            if (!(objectid &&
                  objectid.length) &&
                  glob.document) {
                objectid = document.location.href;
            }

            uuid = makeUUID();

            var action = {
                "id": uuid,
                "timestamp": new Date().toISOString(),
                "actor": cloneObject(actor),
                "verb": {
                    id: verbid
                },
                "object": {
                    id: objectid
                }
            };

            if (Object.getOwnPropertyNames(context).length) {
                action.context = cloneObject(context);
            }

            if (result && typeof result === "object") {
                var bad = false,
                    aOKResults = ["score", "completion", "success",
                                  "response", "duration", "extensions"];

                // check for valid fields
                arv = Object.getOwnPropertyNames(result);
                arv.some(function (n) {
                    if (aOKResults.indexOf(n) < 0) {
                        bad = true;
                        return bad;
                    }
                });

                if (!bad) {
                    action.result = cloneObject(result);
                }
            }

            uuidMap[uuid] = stream.length;
            stream.push(action);
        }

        return uuid;
    }

    /**
     * returns the last *completed* statement on the activity stream
     */
    function lastAction() {
        return stream[stream.length - 1];
    }

    /**
     * Sets a Activities State Doucment
     *
     * The function expects a UUID as stateId. This uuid must be the
     * same UUID as the action UUID relating to this state.
     *
     * Therefore, stateId should be read as "statementId".
     *
     * The document inherits the actor and the object from the originating
     * action statement.
     */
    function setStateDoc(stateid, doc) {
        if (typeof doc === "object" &&
            uuidMap.hasOwnProperty(stateid)) {
            // we accept only a state for complete actions
            var state = stream[uuidMap[stateid]];
            stateDocs[stateid] = {
                "agent": JSON.stringify(state.actor),
                "activityId": state.object.id,
                "stateId": stateid,
                "doc": doc
            };
        }
    }

    /**
     * returns an activity state document for a given stateId.
     *
     * This function will not lookup state documents from the server!
     */
    function getStateDoc(stateid) {
        if (stateDocs.hasOwnProperty(stateid)) {
            return stateDocs[stateid].doc;
        }
    }

    /**
     * callback functions for network requrests
     */

    /**
     * push the *new* actions in the activity stream to the server.
     *
     * This function forbids 2 simultaneous transmissions to the LRS.
     */
    function pushState() {
        function cbPushStateOK() {
            return;
        }
        function cbStateError(xhr, msg) {
            return;
        }

        if (jq &&
            RSD) {

            Object.getOwnPropertyNames(stateDocs).forEach(function (uuid,i) {
                var aParam = {
                    agent: stateDocs[uuid].agent,
                    activityId: stateDocs[uuid].activityId,
                    stateId: stateDocs[uuid].stateId
                };

                var url = RSD.serviceURL("gov.adlnet.xapi",
                                         ["activities", "state"],
                                         aParam);
                if (url) {
                    jq.ajax({
                        type: "PUT",
                        url: url,
                        dataType: 'json',
                        contentType: 'application/json',
                        success: cbPushStateOK,
                        error: cbStateError,
                        data: JSON.stringify(stateDocs[uuid].doc)
                    });
                }
                else {
                    throw new Error("RSD not initialized or service not available");
                }
            });
        }
    }

    /**
     * Helper function to complete pushing statements to the server.
     *
     * This is function will forward any state documents to the server, if present.
     */
    function pushSuccess() {
        bSync = false;
        oldStream = oldStream.concat(upstream);
        upstream = [];
        pushState();
    }

    /**
     * helper to reset the transmission lock.
     */
    function cbError(req) {
        bSync = false;
    }

    /**
     * loads the actor data from the local PowerTLA service.
     * The local actor is the default actor, if no other actor is set
     * via setActor();
     *
     * Note that this function is called internally to identify the
     * presently authenticated actor. Therefore, it is not necessary to
     * initialize the actor if only actions for the local user are
     * called.
     */
    function initLocalActor() {
        function cbLoadActorSuccess(data) {
            if (data &&
                data.id) {
                var url = RSD.serviceURL("org.ieee.papi", ["user", data.id]);

                if (url) {
                    localActor = {
                        "objectType": "Agent",
                        "openid": url
                    };

                    if (!actor ||
                        !actor.objectType) {
                        actor = localActor;
                    }
                    jq(document).trigger("xapiready");
                }
            }
        }

        var idurl = RSD.serviceURL("org.ieee.papi");

        // now we have the link, fetch the data
        if (idurl &&
            jq) {
            jq.ajax({
                type: "GET",
                url: idurl,
                dataType: 'json',
                success: cbLoadActorSuccess,
                error: cbError
            });
        }
        else {
            throw new Error("RSD not initialized or service not available");
        }
    }

    /**
     * generic function to download any activity stream from the LRS.
     */
    function fetchStream(options, cbFunc, bind) {
        if (!bind) {
            bind = LRS;
        }

        function fetchSuccess(data) {
            cbFunc.call(bind, data);
        }

        if (jq && RSD) {
            var uri = RSD.serviceURL("gov.adlnet.xapi",
                                     ["statements"],
                                     options);
            if (uri) {
                jq.ajax({
                    type: "GET",
                    url: uri,
                    dataType: 'json',
                    success: fetchSuccess,
                    error: function () {
                        cbFunc.call(bind);
                    }
                });
            }
            else {
                throw new Error("RSD not initialized or service not available");
            }
        }
    }

    /**
     * download the activity stream for the active user from the server.
     *
     * This will fetch the activity stream of the presently authenticated actor
     * (aka localActor) and NOT the actor set by setActor().
     *
     * Note that this function will override any actor setting provided
     * in the options.
     *
     * This function will alter the internal activity stream.
     */
    function fetchMyStream(options, cbFunc, bind) {
        if (!bind) {
            bind = LRS;
        }

        if (!localActor || !localActor.objectType) {
            throw "no authorized actor";
        }

        if (!options) {
            options = {};
        }

        function cbSuccess(stream) {
            oldStream = stream;
            if (typeof cbFunc === "function") {
                cbFunc.call(bind, stream);
            }
        }

        options.agent = JSON.stringify(localActor);
        fetchStream(options, cbSuccess, LRS);
    }

    /**
     * Variant for fetching the activitiy stream for the set actor.
     *
     * Note that this function will override any actor setting provided
     * in the options.
     *
     * This function will alter the internal activity stream.
     */
    function fetchActorStream(options, cbFunc, bind) {
        if (!bind) {
            bind = LRS;
        }

        if (!actor || !actor.objectType) {
            throw "no actor set"; // should never happen
        }

        if (!options) {
            options = {};
        }

        function cbSuccess(stream) {
            oldStream = stream;
            if (typeof cbFunc === "function") {
                cbFunc.call(bind, stream);
            }
        }

        options.agent = JSON.stringify(actor);
        fetchStream(options, cbSuccess, LRS);
    }

    /**
     * uploads the activity stream to the server
     */
    function pushStream() {
        // don't push if another push is in progress
        // also don't push if no local actor is present
        if (!localActor || !localActor.objectType) {
            throw "no authorized actor";
        }

        if (jq &&
            RSD &&
            !bSync) {
            bSync = true;

            if (upstream) {
                upstream = upstream.concat(stream);
            }
            else {
                upstream = stream;
            }

            // flush the stream
            stream  = [];
            uuidMap = {};

            var URI = RSD.serviceURL("gov.adlnet.xapi", ["statements"]);

            if (URI) {
                jq.ajax({
                    type: "POST",
                    url: URI,
                    dataType: 'json',
                    contentType: 'application/json',
                    success: pushSuccess,
                    error: cbError,
                    data: JSON.stringify(upstream)
                });
            }
            else {
                throw new Error("RSD not initialized or service not available");
            }
        }
    }

    /**
     * fetch state document from the server
     */
    function fetchState(cbDocument, objectId, stateId, agent) {

        function cbFetchStateOK (retDoc) {
            if (typeof cbDocument === "function") {
                cbDocument.call(LRS, retDoc);
            }
        }

        function cbFetchError(xhr, m) {
            return;
        }

        if (jq &&
            RSD) {
            if (!agent) {
                agent = localActor;
            }

            var param = {
                stateId: stateId,
                activityId: objectId
            };
            if (typeof agent === "object") {
                param.agent = agent;
            }

            var url = RSD.serviceURL( "gov.adlnet.xapi",
                                     ["activities", "state"],
                                     param);

            if (url) {
                jq.ajax({
                    type: "GET",
                    url: url,
                    dataType: 'json',
                    success: cbFetchStateOK,
                    error: cbFetchError
                });
            }
            else {
                throw new Error("RSD not initialized or service not available");
            }
        }
    }

    /**
     * Local Storage Persistency Layer
     */

    /**
     * store the locally added actions and statedocs to localStorage.
     */
    function storeStream() {
        if (glob.localStorage) {
            glob.localStorage.setItem("prwtlaXAPIStream", JSON.stringify(stream));
            glob.localStorage.setItem("pwrtlaXAPIState", JSON.stringify(stateDocs));
            glob.localStorage.setItem("pwrtlaXAPIOngoing", JSON.stringify(ongoing));
        }
    }

    /**
     * load stream and state documents from localStorage.
     */
    function loadStream() {
        if (glob.localStorage) {
            var sStream   = glob.localStorage.setItem("prwtlaXAPIStream"),
                sState    = glob.localStorage.getItem("pwrtlaXAPIState"),
                sOngoing  = glob.localStorage.getItem("pwrtlaXAPIOngoing");

            if (sStream &&
                sStream.length) {
                var tNStream;
                try {
                    tNStream = JSON.parse(sStream);
                }
                catch (err) {
                    tNStream = [];
                }

                if (!tNStream) {
                    tNStream = [];
                }
                stream.concat(tNStream);
                stream.forEach(function (a, i) {
                    uuidMap[a.id] = i;
                });
            }

            if (sState &&
                sState.length) {

                var tObj;
                try {
                    tObj = JSON.parse(sState);
                }
                catch (err) {
                    tObj = {};
                }
                Object.getOwnPropertyNames(tObj).forEach(function (nm) {
                    stateDocs[nm] = tObj[nm];
                });
            }

            if (sOngoing &&
                sOngoing.length) {
                var tOStream = JSON.parse(sOngoing);
                try {
                    tOStream = JSON.parse(sOngoing);
                }
                catch (err) {
                    tOStream = [];
                }

                if (!tOStream) {
                    tOStream = [];
                }
                ongoing.concat(tOStream);
            }
        }
    }

    /**
     * remove all stream data from local storage.
     */
    function eraseStream() {
        if (glob.localStorage) {
            glob.localStorage.setItem("prwtlaXAPIStream", "[]");
            glob.localStorage.setItem("pwrtlaXAPIState", "{}");
            glob.localStorage.setItem("pwrtlaXAPIOngoing", "[]");
        }

        stateDocs = {};
        stream = [];
    }

    /**
     * enable finishing unfinished business on unload.
     */
    function enableAutoFinish() {
        autoFinish = true;
    }

    /**
     * disable finishing unfinished business on unload.
     */
    function disableAutoFinish() {
        autoFinish = true;
    }

    /**
     * internal helper to push the activity stream before leaving the page.
     */
    function cbUnload() {
        // presently we don't support long running actions
        // finish all unfinished business.
        if (autoFinish) {
            Object.getOwnPropertyNames(ongoing).forEach(function (actUUID) {
                finishAction(actUUID);
            });
        }

        pushStream();
    }

    /**
     * internal initialization function
     */
    function init() {
        // ensure that we catch all actions, even if the user reloads the page

        jq(document).bind("unload",       cbUnload);
        jq(document).bind("beforeunload", cbUnload);

        initLocalActor();
    }

    /**
     * register a callback for flexible readiness registration.
     */
    function readyInit(cbReady) {
        if (typeof cbReady === "function") {
            if (localActor &&
                localActor.hasOwnProperty("objectType")) {
                cbReady.call(glob.document);
            }
            else {
                jq(glob.document).bind("xapiready", cbReady);
            }
        }
        else {
            throw new Error("ready requires a callback function as parameter 1");
        }
    }

    /** ******************************************************************
     * Define external accessors
     */
    LRS.ready         = readyInit;

    LRS.setActor      = setActor;
    LRS.unsetActor    = unsetActor;

    LRS.startContext  = startContext;
    LRS.endContext    = endContext;
    LRS.clearContext  = resetContext;
    LRS.getContext    = getContext;

    LRS.startAction   = startAction;
    LRS.finishAction  = finishAction;
    LRS.recordAction  = recordAction;

    LRS.getStream     = getStream;
    LRS.lastAction    = lastAction;

    // TODO add State Document API
    LRS.setStateDoc   = setStateDoc;
    LRS.getStateDoc   = getStateDoc;

    LRS.fetchMyActions    = fetchMyStream;
    LRS.fetchUserActions  = fetchActorStream;
    LRS.fetchActions      = fetchStream;
    LRS.fetchState        = fetchState;

    LRS.push              = function () {this.pushStream(); this.pushState();};
    LRS.pushStream        = pushStream;
    LRS.pushState         = pushState;

    LRS.store             = storeStream;
    LRS.load              = loadStream;
    LRS.flush             = eraseStream;

    LRS.enableAutoFinish  = enableAutoFinish;
    LRS.disableAutoFinish = disableAutoFinish;

    /** ******************************************************************
     * Expose the LRS API
     */

    if (glob.define &&
        glob.define.amd) {
        // RequireJS  stuff
        glob.define(["jquery", "rsd"], function (jQuery, rsd) {
            jq = jQuery;
            RSD = rsd;
            RSD.ready(init);
            return LRS;
        });
    }
    else {
        // any other environment
        if (glob.jQuery) {
            jq = glob.jQuery;
            RSD = glob.rsd;
            RSD.ready(init);
        }
        glob.lrs = LRS;
    }
} ((function () {
    if (window) {
            return window;
        }
        if (global) {
            return global;
        }
        return {};
}())));
