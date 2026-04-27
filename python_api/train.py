import argparse
import json
from pathlib import Path
from typing import List, Tuple

import numpy as np
import tensorflow as tf
import tensorflow_datasets as tfds
from sklearn.metrics import classification_report, confusion_matrix


def build_datasets(
    data_dir: Path, image_size: int, batch_size: int
) -> Tuple[tf.data.Dataset, tf.data.Dataset, tf.data.Dataset, List[str]]:
    (ds_train, ds_val, ds_test), info = tfds.load(
        "plant_village",
        split=["train[:80%]", "train[80%:90%]", "train[90%:]"],
        as_supervised=True,
        with_info=True,
        data_dir=str(data_dir),
    )
    class_names = list(info.features["label"].names)

    def preprocess(image, label):
        image = tf.image.resize(image, (image_size, image_size))
        image = tf.cast(image, tf.float32)
        return image, label

    autotune = tf.data.AUTOTUNE
    ds_train = (
        ds_train.shuffle(2048)
        .map(preprocess, num_parallel_calls=autotune)
        .batch(batch_size)
        .prefetch(autotune)
    )
    ds_val = (
        ds_val.map(preprocess, num_parallel_calls=autotune)
        .batch(batch_size)
        .prefetch(autotune)
    )
    ds_test = (
        ds_test.map(preprocess, num_parallel_calls=autotune)
        .batch(batch_size)
        .prefetch(autotune)
    )
    return ds_train, ds_val, ds_test, class_names


def build_model(num_classes: int, image_size: int) -> tf.keras.Model:
    data_augmentation = tf.keras.Sequential(
        [
            tf.keras.layers.RandomFlip("horizontal"),
            tf.keras.layers.RandomRotation(0.1),
            tf.keras.layers.RandomZoom(0.1),
        ],
        name="augmentation",
    )

    inputs = tf.keras.Input(shape=(image_size, image_size, 3))
    x = data_augmentation(inputs)
    x = tf.keras.applications.efficientnet.preprocess_input(x)
    base_model = tf.keras.applications.EfficientNetB0(
        include_top=False, weights="imagenet", input_tensor=x, pooling="avg"
    )
    base_model.trainable = False
    x = tf.keras.layers.Dropout(0.2)(base_model.output)
    outputs = tf.keras.layers.Dense(num_classes, activation="softmax")(x)
    model = tf.keras.Model(inputs, outputs, name="plantvillage_effnetb0")
    return model


def evaluate_and_report(
    model: tf.keras.Model,
    ds_test: tf.data.Dataset,
    class_names: List[str],
    reports_dir: Path,
) -> None:
    reports_dir.mkdir(parents=True, exist_ok=True)
    y_true = []
    y_pred = []
    for batch_images, batch_labels in ds_test:
        preds = model.predict(batch_images, verbose=0)
        y_true.extend(batch_labels.numpy().tolist())
        y_pred.extend(np.argmax(preds, axis=1).tolist())

    report = classification_report(y_true, y_pred, target_names=class_names, digits=4)
    (reports_dir / "classification_report.txt").write_text(report, encoding="utf-8")

    cm = confusion_matrix(y_true, y_pred)
    np.savetxt(reports_dir / "confusion_matrix.csv", cm, delimiter=",", fmt="%d")

    metrics = {
        "test_accuracy": float(np.mean(np.array(y_true) == np.array(y_pred))),
        "classes": len(class_names),
    }
    (reports_dir / "metrics.json").write_text(json.dumps(metrics, indent=2), encoding="utf-8")


def main() -> None:
    parser = argparse.ArgumentParser(description="Train PlantVillage CNN (TensorFlow).")
    parser.add_argument(
        "--data-dir",
        default=str(Path(__file__).resolve().parent / "data" / "tfds"),
        help="TFDS data directory.",
    )
    parser.add_argument("--image-size", type=int, default=224)
    parser.add_argument("--batch-size", type=int, default=32)
    parser.add_argument("--epochs", type=int, default=8)
    parser.add_argument(
        "--model-out",
        default=str(Path(__file__).resolve().parent / "models" / "plantvillage_best.keras"),
    )
    parser.add_argument(
        "--labels-out",
        default=str(Path(__file__).resolve().parent / "models" / "labels.json"),
    )
    parser.add_argument(
        "--reports-dir",
        default=str(Path(__file__).resolve().parent / "reports"),
    )
    args = parser.parse_args()

    data_dir = Path(args.data_dir)
    model_out = Path(args.model_out)
    labels_out = Path(args.labels_out)
    reports_dir = Path(args.reports_dir)
    model_out.parent.mkdir(parents=True, exist_ok=True)

    ds_train, ds_val, ds_test, class_names = build_datasets(
        data_dir, args.image_size, args.batch_size
    )
    model = build_model(len(class_names), args.image_size)
    model.compile(
        optimizer=tf.keras.optimizers.Adam(1e-3),
        loss=tf.keras.losses.SparseCategoricalCrossentropy(),
        metrics=["accuracy"],
    )

    callbacks = [
        tf.keras.callbacks.ModelCheckpoint(
            str(model_out), monitor="val_accuracy", save_best_only=True, verbose=1
        ),
        tf.keras.callbacks.EarlyStopping(monitor="val_accuracy", patience=3, restore_best_weights=True),
    ]

    model.fit(ds_train, validation_data=ds_val, epochs=args.epochs, callbacks=callbacks)

    model.save(str(model_out))
    labels_out.write_text(json.dumps(class_names, indent=2), encoding="utf-8")
    evaluate_and_report(model, ds_test, class_names, reports_dir)

    print("Training complete.")
    print(f"Model: {model_out}")
    print(f"Labels: {labels_out}")
    print(f"Reports: {reports_dir}")


if __name__ == "__main__":
    main()
