<?php echo $sender['Profile']['first_name']; ?> <?php echo $sender['Profile']['last_name']; ?> (<?php echo $sender['User']['username']; ?>)
has commented on a password on <?php echo date('M d,Y \a\t H:i', strtotime($resource['Secret'][0]['modified'])); ?>

Name : <?php echo $resource['Resource']['name']; ?>
<?php if(Configure::read('EmailNotification.show.username')) : ?>
Username : <?php echo $resource['Resource']['username']; ?>
<?php endif; ?>
Url : <?php echo $resource['Resource']['uri']; ?>
Description : <?php echo $resource['Resource']['description']; ?>
