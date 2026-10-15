import 'package:flutter/material.dart';

/// Selection belongs to its list; the shell renders its actions in the main app bar.
class ConversationHeader {
  final String title;
  final Widget actions;
  final VoidCallback back;
  const ConversationHeader(this.title, this.actions, this.back);
  static final states = ValueNotifier<Map<String, ConversationHeader>>({});
  static void set(String key, ConversationHeader? value) {
    states.value = {...states.value}..remove(key);
    if (value != null) states.value = {...states.value, key: value};
  }
}
