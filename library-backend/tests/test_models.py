import pytest
from pydantic import ValidationError

from app.models import RecordBatch, RecordPacket, SourcePacket


def test_record_normalizes_duplicate_tags():
    record = RecordPacket(record_id="wp:1", source_key="wordpress", object_type="post", title="A", tags=["Energy", " energy ", "Grid"])
    assert record.tags == ["Energy", "Grid"]


def test_batch_rejects_unknown_schema():
    with pytest.raises(ValidationError):
        RecordBatch(schema="unknown", source=SourcePacket(source_key="wordpress", name="WordPress"), records=[RecordPacket(record_id="wp:1", source_key="wordpress", object_type="post", title="A")])
