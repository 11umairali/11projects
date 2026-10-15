// ChatCache usage guide. This file intentionally contains documentation only.
// The production implementation lives in screens/friend_chat_page.dart.
//
// ChatCache exposes static read/write methods; there is no ChatCache.I singleton.
// Always derive an account key from both the server and signed-in identity:
//
//   final account = ChatCache.accountKey(apiBase, session.userId, session.token);
//   final cached = account == null
//       ? <Map<String, dynamic>>[]
//       : await ChatCache.read(account, peerId);
//
// After fetching messages, write the snapshot with the same account and peer:
//
//   if (account != null) await ChatCache.write(account, peerId, freshMessages);
//
// Before updating UI after an await, check mounted and that the active account
// and conversation are still the ones that initiated the fetch. Do not derive
// cache keys from a user ID alone: different servers may reuse the same IDs.
// Session.signOut clears cached messages with await ChatCache.clear().
