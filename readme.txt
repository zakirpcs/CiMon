<div align="center">

<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
	<rect x="2" y="3" width="20" height="7" rx="1.5"/>
	<circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/>
	<rect x="2" y="14" width="20" height="7" rx="1.5"/>
	<polyline points="5 17.5 7 17.5 8.5 14.5 10.5 20.5 12.5 14.5 14 17.5 19 17.5"/>
</svg>

# CiMon

**Modern Web UI Theme for Nagios **

</div>

---

## Overview

CiMon is a modern and feature-rich web UI that provides real-time dashboards, enhanced monitoring views, advanced filtering, and a much better user experience..

---

## ✨ Features

| Category | Capabilities |
|---|---|
| 🏠 **Dashboard** | Mordern Dashboard with global search feature and all problematic services in Dashboard|
| ⚙ **Host** | Compact Host deatils|
| 🛡️ **Service** | Colapsible service details |
| 🔔 **Notifications** | Clean Notification view |
| 🎨 **UI** | Clean, responsive JS/CSS — no frontend framework required |

---

## 🖥️ Screenshots

---

## 🚀 Installation

### Download CiMon zip (recommended for production)

Upload CiMon package into your nagios server extarct it then move CiMon  directory into /usr/local/nagios directory
then rename existing theme.

```bash
unzip CiMon-main.zip -d /usr/local/nagios/
cd /usr/local/nagios/
mv share share.old
mv CiMon-main share
```

## Restart Nagios & web service
```bash
systemctl restart nagios httpd
```
