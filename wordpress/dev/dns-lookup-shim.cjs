// Dev only. On this machine Node's DNS server list is 127.0.0.1, so wp-env's dns.resolve calls
// fail with "network is not available". This routes dns.resolve* through dns.lookup (the OS resolver).
// Usage: NODE_OPTIONS="--require ./wordpress/dev/dns-lookup-shim.cjs" npx wp-env start
const dns = require("dns");
const viaLookup = (family) => (host, ...args) => {
  const cb = args.pop();
  dns.lookup(host, { family, all: true }, (err, addrs) => (err ? cb(err) : cb(null, addrs.map((a) => a.address))));
};
dns.resolve4 = viaLookup(4);
dns.resolve6 = viaLookup(6);
dns.resolve = (host, rrtype, cb) => {
  if (typeof rrtype === "function") { cb = rrtype; rrtype = "A"; }
  return (rrtype === "AAAA" ? dns.resolve6 : dns.resolve4)(host, cb);
};
const p = dns.promises;
p.resolve4 = (h) => p.lookup(h, { family: 4, all: true }).then((a) => a.map((x) => x.address));
p.resolve6 = (h) => p.lookup(h, { family: 6, all: true }).then((a) => a.map((x) => x.address));
p.resolve = (h, t) => (t === "AAAA" ? p.resolve6 : p.resolve4)(h);
