@echo off
setlocal EnableExtensions
cd /d "%~dp0.."

if not exist "src\Bundles\MediaPack.php" (
  echo [ERREUR] media7_lab doit etre directement dans la racine du repo taxt_fomata.
  echo Exemple: taxt_fomata\media7_lab\start.bat
  pause
  exit /b 1
)

set "PHP_CMD=php"
where php >nul 2>nul
if not errorlevel 1 goto :php_ready

set "PHP_DIR=%CD%\.tools\php-8.5.10"
set "PHP_EXE=%PHP_DIR%\php.exe"
set "PHP_ZIP=%TEMP%\media7-php-8.5.10.zip"

if exist "%PHP_EXE%" (
  set "PHP_CMD=%PHP_EXE%"
  goto :php_ready
)

echo.
echo PHP n'est pas installe. MEDIA7 LAB va telecharger une copie portable officielle de PHP.
echo Aucun droit administrateur n'est necessaire.
echo Taille: environ 35 Mo.
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; $ProgressPreference='SilentlyContinue'; $url='https://downloads.php.net/~windows/releases/archives/php-8.5.10-nts-Win32-vs17-x64.zip'; $zip='%PHP_ZIP%'; $dir='%PHP_DIR%'; Invoke-WebRequest -UseBasicParsing $url -OutFile $zip; $hash=(Get-FileHash $zip -Algorithm SHA256).Hash.ToLowerInvariant(); if($hash -ne '22ec430195984d233eb9e62c637a945bbcda06efca2f392d9d96d62c6acd34f8'){ throw ('SHA256 inattendu: ' + $hash) }; New-Item -ItemType Directory -Force -Path $dir | Out-Null; Expand-Archive -Path $zip -DestinationPath $dir -Force"

if errorlevel 1 (
  echo.
  echo [ERREUR] Le telechargement ou l'extraction de PHP a echoue.
  echo Envoie une capture de cette fenetre.
  pause
  exit /b 1
)

set "PHP_CMD=%PHP_EXE%"

:php_ready
"%PHP_CMD%" -v >nul 2>nul
if errorlevel 1 (
  echo.
  echo [ERREUR] PHP est present mais ne demarre pas.
  echo Le runtime Microsoft Visual C++ 2015-2022 x64 peut etre manquant.
  echo Envoie une capture de cette fenetre.
  pause
  exit /b 1
)

echo.
echo MEDIA7 LAB: http://127.0.0.1:8080/
echo Ferme cette fenetre ou fais Ctrl+C pour arreter.
echo.
start "" "http://127.0.0.1:8080/"
"%PHP_CMD%" -S 127.0.0.1:8080 -t media7_lab

if errorlevel 1 (
  echo.
  echo [ERREUR] Le serveur PHP s'est arrete.
  pause
)
