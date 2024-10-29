CREATE TABLE User_Pokemon_Favorites ( 
    `id` int auto_increment not null PRIMARY key,
    `user_id` int,
    `pokemon_id` int,
    `created` timestamp default current_timestamp,
    `modified` timestamp default current_timestamp on update current_timestamp,
    FOREIGN KEY (`user_id`) REFERENCES Users(`id`),
    FOREIGN KEY (`pokemon_id`) REFERENCES SC_Pokemon(`id`),
    unique key (`user_id`, `pokemon_id`)
)
