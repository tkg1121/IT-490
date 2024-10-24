CREATE TABLE SC_PokemonImages( 
    `id`             int auto_increment not null,
    `image_id`       INT,
    `created`        timestamp default current_timestamp,
    `modified`       timestamp default current_timestamp on update current_timestamp,
    PRIMARY KEY (`id`),
    FOREIGN KEY(`image_id`) REFERENCES SC_Pokemon(`id`),
    UNIQUE KEY(`image_id`)
)
