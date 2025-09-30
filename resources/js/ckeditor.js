import { ClassicEditor, Essentials, Bold, Italic, Link, List, ListProperties, Image, ImageToolbar, ImageUpload, ImageResize, Table, TableToolbar, MediaEmbed, BlockQuote, Undo, Heading, Indent, IndentBlock, Alignment, Font, PasteFromOffice, SourceEditing, SimpleUploadAdapter } from 'ckeditor5';

window.ClassicEditor = ClassicEditor;
window.ClassicEditor.builtinPlugins = [
    Essentials,
    Bold,
    Italic,
    Link,
    List,
    ListProperties,
    Image,
    ImageToolbar,
    ImageUpload,
    ImageResize,
    Table,
    TableToolbar,
    MediaEmbed,
    BlockQuote,
    Undo,
    Heading,
    Indent,
    IndentBlock,
    Alignment,
    Font,
    PasteFromOffice,
    SourceEditing,
    SimpleUploadAdapter
];
