import 'package:flutter/material.dart';
import '../core/widgets.dart';
import 'person_profile_page.dart';

class ProfileAvatar extends StatelessWidget {
  final Map<String, dynamic> user;
  final double radius;
  const ProfileAvatar(this.user, {super.key, this.radius = 22});
  @override
  Widget build(BuildContext context) => InkWell(
    onTap: () => pushPage(context, PersonProfilePage(userId: user['id'] as int)),
    customBorder: const CircleBorder(),
    child: Avatar('${user['name']}', url: user['avatar_url'] as String?, radius: radius),
  );
}
