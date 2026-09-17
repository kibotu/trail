package net.kibotu.trail.shared.entry

import java.util.Collections

/**
 * The entries the user has seen recently, so opening one renders its card on the very first frame.
 *
 * This is what makes the list-to-detail shared element work: the shared bounds can only match if
 * both cards exist when the transition starts, and waiting for the network would leave the detail
 * screen holding a shimmer at that moment. Loading still happens, it just refreshes a card that is
 * already on screen instead of replacing a placeholder.
 */
object EntryCache {

    private const val MaxSize = 200

    private val entries: MutableMap<String, Entry> = Collections.synchronizedMap(
        object : LinkedHashMap<String, Entry>(0, 0.75f, true) {
            override fun removeEldestEntry(eldest: Map.Entry<String, Entry>) = size > MaxSize
        }
    )

    operator fun get(hashId: String): Entry? = entries[hashId]

    fun put(entry: Entry) {
        entries[entry.hashId ?: return] = entry
    }

    fun putAll(values: List<Entry>) = values.forEach(::put)
}
