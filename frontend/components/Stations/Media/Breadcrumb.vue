<template>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a
                    href="#"
                    @click.prevent="changeDirectory('')"
                >{{ $gettext('Home') }}</a>
            </li>
            <template
                v-for="part in directoryParts"
                :key="part.dir"
            >
                <li class="breadcrumb-item">
                    <a
                        href="#"
                        @click.prevent="changeDirectory(part.dir)"
                    >{{ part.display }}</a>
                </li>
            </template>
        </ol>
    </nav>

    <h3
        id="breadcrumb"
        class="card-subtitle mt-0 mb-2"
    />
</template>

<script setup lang="ts">
import {computed} from "vue";

const props = defineProps<{
    currentDirectory: string,
}>();

const emit = defineEmits<{
    (e: 'change-directory', newDir: string): void
}>();

interface DirPart {
    dir: string,
    display: string
}

const directoryParts = computed<DirPart[]>(() => {
    const dirParts: DirPart[] = [];

    if (props.currentDirectory === '') {
        return dirParts;
    }

    let builtDir = '';
    const dirSegments = props.currentDirectory.split('/');

    dirSegments.forEach((part) => {
        if (builtDir === '') {
            builtDir += part;
        } else {
            builtDir += '/' + part;
        }

        dirParts.push({dir: builtDir, display: part});
    });

    return dirParts;
});

const changeDirectory = (newDir: string) => {
    emit('change-directory', newDir);
}
</script>
