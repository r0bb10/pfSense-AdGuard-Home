# pfSense AdGuard Home

A native pfSense package for the official AdGuard Home FreeBSD amd64 binary.

Install or force-refresh the current stable package from a pfSense shell:

```sh
curl -fL -o /tmp/pfSense-pkg-adguardhome-stable.pkg https://github.com/r0bb10/pfSense-AdGuard-Home/releases/latest/download/pfSense-pkg-adguardhome-stable.pkg

pkg add -f /tmp/pfSense-pkg-adguardhome-stable.pkg
```

Released as stable with the updated binary inside, later updates can be handled inside AdGuard own update flow.

Open **Services > AdGuard Home** to enable the service and **Status > AdGuard Home** to view its running version and admin UI.
