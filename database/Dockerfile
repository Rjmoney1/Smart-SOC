services:
  mysql:
    image: mysql:8
    container_name: mysql-container
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: root123
      MYSQL_DATABASE: cyber_ai
    ports:
      - "3306:3306"

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    container_name: phpmyadmin-container
    restart: always
    ports:
      - "8080:80"
    environment:
      PMA_HOST: mysql-container
      PMA_PORT: 3306
      PMA_USER: root
      PMA_PASSWORD: root123