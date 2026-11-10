@echo off
cd /d "%~dp0"
echo.
echo  Ihira Live - acces depuis un telephone (meme Wi-Fi que ce PC)
echo  Ouvrez l'une de ces adresses sur le telephone :
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4"') do echo     http://%%a:8000
echo.
echo  Diffusion : ajouter  /display/   a la fin de l'adresse
echo  Interface : ajouter  /login.php  a la fin de l'adresse
echo  (fermer cette fenetre pour arreter)
echo.
php -d upload_max_filesize=32M -d post_max_size=64M -d memory_limit=256M -S 0.0.0.0:8000 -t public
pause
