import { WebAuthnAbortService as w, startAuthentication as f, startRegistration as g, browserSupportsWebAuthnAutofill as b, browserSupportsWebAuthn as E } from "@simplewebauthn/browser";
class i extends Error {
  constructor(t) {
    super(t), this.name = "PasskeyError";
  }
}
class d extends i {
  constructor() {
    super("Passkeys are not supported in this browser."), this.name = "NotSupportedError";
  }
}
class S extends i {
  constructor() {
    super("The passkey operation was cancelled."), this.name = "UserCancelledError";
  }
}
class k extends i {
  constructor() {
    super("This device is already registered as a passkey."), this.name = "PasskeyExistsError";
  }
}
class v extends i {
  constructor(t) {
    const s = t ?? "this domain";
    super(
      `Passkeys can't be used on ${s}. For local development, use localhost.`
    ), this.name = "InvalidDomainError";
  }
}
const c = (e) => {
  if (e instanceof i)
    return e;
  if (!(e instanceof Error))
    return new i("An unknown error occurred.");
  if (R(e))
    return new v(N());
  switch (e.name) {
    case "NotAllowedError":
      return new S();
    case "InvalidStateError":
      return new k();
    case "NotSupportedError":
      return new d();
    default:
      return new i(e.message);
  }
}, R = (e) => A(e) === "ERROR_INVALID_DOMAIN", A = (e) => "code" in e && typeof e.code == "string" ? e.code : void 0, N = () => typeof globalThis.location?.hostname == "string" && globalThis.location.hostname.length > 0 ? globalThis.location.hostname : void 0;
let n = {};
const O = (e) => {
  n = {
    ...n,
    ...e,
    fetch: {
      ...n.fetch,
      ...e.fetch,
      headers: {
        ...n.fetch?.headers,
        ...e.fetch?.headers
      }
    }
  };
}, T = () => typeof document > "u" ? null : C() || I(), C = () => {
  const e = document.querySelector('meta[name="csrf-token"]');
  if (!e)
    return null;
  const t = e.getAttribute("content");
  return t ? { header: "X-CSRF-TOKEN", value: t } : null;
}, I = () => {
  const e = "XSRF-TOKEN=", t = document.cookie.split("; ").find((o) => o.startsWith(e));
  if (!t)
    return null;
  const s = t.slice(e.length);
  return s ? { header: "X-XSRF-TOKEN", value: decodeURIComponent(s) } : null;
}, u = async (e) => {
  const t = await fetch(e, {
    method: "GET",
    headers: {
      Accept: "application/json",
      ...n.fetch?.headers
    },
    credentials: n.fetch?.credentials ?? "same-origin"
  });
  return t.ok || await m(t), t.json();
}, l = async (e, t) => {
  const s = T(), o = {
    "Content-Type": "application/json",
    Accept: "application/json",
    ...n.fetch?.headers
  };
  s && (o[s.header] = s.value);
  const r = await fetch(e, {
    method: "POST",
    headers: o,
    credentials: n.fetch?.credentials ?? "same-origin",
    body: JSON.stringify(t)
  });
  return r.ok || await m(r), r.json();
}, m = async (e) => {
  let t = `Request failed with status ${e.status}`;
  try {
    const s = await e.json();
    s && typeof s == "object" && "message" in s && typeof s.message == "string" && (t = s.message);
  } catch {
  }
  throw new Error(t);
}, h = (e) => (typeof e == "function" ? e() : e) ?? !1, a = {
  registerOptions: "/user/passkeys/options",
  registerStore: "/user/passkeys",
  verifyOptions: "/passkeys/login/options",
  verifySubmit: "/passkeys/login"
}, j = {
  /**
   * Configure the passkeys client.
   */
  configure(e) {
    O(e);
  },
  /**
   * Check if the browser supports passkeys.
   */
  isSupported() {
    return E();
  },
  /**
   * Check if the browser supports passkey autofill.
   */
  async isAutofillSupported() {
    return b();
  },
  /**
   * Register a new passkey for the authenticated user.
   */
  async register(e) {
    if (!this.isSupported())
      throw new d();
    this.cancel();
    try {
      const t = p(e, {
        options: a.registerOptions,
        submit: a.registerStore
      }), { options: s } = await u(t.optionsRoute), o = await g({ optionsJSON: s }), r = {
        name: e.name,
        credential: o
      };
      return await l(
        t.submitRoute,
        r
      );
    } catch (t) {
      throw c(t);
    }
  },
  /**
   * Verify with a passkey.
   */
  async verify(e = {}) {
    if (!this.isSupported())
      throw new d();
    this.cancel();
    try {
      const t = p(e, {
        options: a.verifyOptions,
        submit: a.verifySubmit
      }), { options: s } = await u(
        t.optionsRoute
      ), r = {
        credential: await f({ optionsJSON: s }),
        remember: h(e.remember)
      };
      return await l(t.submitRoute, r);
    } catch (t) {
      throw c(t);
    }
  },
  /**
   * Enable passkey autofill on the current page.
   *
   * Note that the page must have an input with `autocomplete="email webauthn"` to
   * anchor the browser's passkey picker dropdown.
   *
   * Returns the verification response on success, or `undefined` if autofill
   * is not supported or was cancelled.
   */
  async autofill(e = {}) {
    if (!(!this.isSupported() || !await this.isAutofillSupported()))
      try {
        const s = p(e, {
          options: a.verifyOptions,
          submit: a.verifySubmit
        }), { options: o } = await u(
          s.optionsRoute
        ), y = {
          credential: await f({
            optionsJSON: o,
            useBrowserAutofill: !0
          }),
          remember: h(e.remember)
        };
        return await l(s.submitRoute, y);
      } catch (s) {
        if (s instanceof Error && ["AbortError", "NotAllowedError"].includes(s.name))
          return;
        throw c(s);
      }
  },
  /**
   * Cancel any pending passkey operation.
   */
  cancel() {
    w.cancelCeremony();
  }
}, p = (e, t) => ({
  optionsRoute: e.routes?.options ?? t.options,
  submitRoute: e.routes?.submit ?? t.submit
});
export {
  v as I,
  d as N,
  i as P,
  S as U,
  k as a,
  j as b,
  a as d,
  h as r,
  c as t
};
