TulipsChat - Setup
1. Folder server pe upload karein.
2. MySQL me database banayen (e.g. tulipschat) aur config.php me db details likhein.
3. Browser me /install.php kholein, admin username/password dein.
4. Install ke baad install.php delete kar dein.
5. uploads/avatars aur data folder writable hone chahiye (chmod 755/775).
Nginx: data/ folder block karein aur uploads me php na chalne dein:
  location ^~ /data/ { deny all; }
  location ~* ^/uploads/.*\.php$ { deny all; }
