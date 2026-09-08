@echo off
setlocal
cd /d "%~dp0.."

if not exist "src\Bundles\MediaPack.php" (
  echo [ERREUR] media7_lab doit etre directement dans la racine du repo taxt_fomata.
  echo Exemple: taxt_fomata\media7_lab\start.bat
  pause
  exit /b 1
)

where php >nul 2>nul
if errorlevel 1 (
  echo [ERREUR] PHP n'est pas trouve dans PATH.
  echo Utilise PHP 8.1+ puis relance ce fichier.
  pause
  exit /b 1
)

echo.
echo MEDIA7 LAB: http://127.0.0.1:8080/
echo Ferme cette fenetre ou fais Ctrl+C pour arreter.
echo.
start "" "http://127.0.0.1:8080/"
php -S 127.0.0.1:8080 -t media7_lab
