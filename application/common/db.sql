CREATE TABLE `wp_op_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `op_uid` int(11) NOT NULL COMMENT '操作用户',
  `op_ip` varchar(64) DEFAULT NULL COMMENT '操作用户ip',
  `to_uid` int(11) DEFAULT NULL COMMENT '被操作用户',
  `desc` varchar(255) DEFAULT NULL COMMENT '操作描述',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8 comment '操作日志';

