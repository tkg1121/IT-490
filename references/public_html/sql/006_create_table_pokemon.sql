CREATE TABLE SC_Pokemon (
    `id`         int auto_increment not null,
    `name`       VARCHAR(30),
    `coverImg`   TEXT,
    `price`      DECIMAL(10,2),
    `type`       VARCHAR(20),
    `hp`         INT,
    `attack`     INT,
    `defense`    INT,
    `created`    timestamp default current_timestamp,
    `modified`   timestamp default current_timestamp on update current_timestamp,
    PRIMARY KEY (`id`),
    UNIQUE KEY(`name`)
)