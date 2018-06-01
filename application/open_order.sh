while true ;do

cd /var/www/hying && php -f /var/www/hying/think.php DoOrder > /dev/null 2>&1

sleep 1

done
