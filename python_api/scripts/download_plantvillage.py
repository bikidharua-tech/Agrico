import argparse
from pathlib import Path

import tensorflow_datasets as tfds


def main() -> None:
    parser = argparse.ArgumentParser(description="Download PlantVillage via TFDS.")
    parser.add_argument(
        "--data-dir",
        default=str(Path(__file__).resolve().parents[1] / "data" / "tfds"),
        help="Directory for TFDS data storage.",
    )
    args = parser.parse_args()
    data_dir = Path(args.data_dir)
    data_dir.mkdir(parents=True, exist_ok=True)

    builder = tfds.builder("plant_village", data_dir=str(data_dir))
    builder.download_and_prepare()
    info = builder.info

    print("PlantVillage downloaded.")
    print(f"TFDS dir: {data_dir}")
    print(f"Split sizes: {info.splits}")
    print(f"Classes: {len(info.features['label'].names)}")


if __name__ == "__main__":
    main()
